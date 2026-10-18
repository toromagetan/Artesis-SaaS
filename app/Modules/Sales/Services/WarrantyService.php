<?php

namespace App\Modules\Sales\Services;

use App\Modules\Accounting\Services\PostingService;
use App\Modules\Accounting\Support\Chart;
use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\BranchContext;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Services\FifoService;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Supplier;
use App\Modules\Platform\Services\AuditLogger;
use App\Modules\Purchasing\Services\PurchaseOrderService;
use App\Modules\Sales\Models\BrokenStock;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleItem;
use App\Modules\Sales\Models\WarrantyCase;
use App\Modules\Sales\Models\WarrantyPayment;
use App\Modules\Sales\Models\WarrantyReplacement;
use App\Support\Money;
use App\Support\Numbering;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Mesin status garansi (paritas alur after-sales Artesis,
 * idiom Lentera): kasus per item penjualan → barang lama ke
 * gudang utama → inspeksi (baik = kembali ke stok lewat gerakan
 * warranty_return; rusak = stok rusak menunggu write-off
 * ber-alasan) → pengganti disiapkan (stok gudang / PO khusus)
 * → dikirim (warranty_out + jurnal Beban Garansi) → diterima
 * cabang (warranty_in) → biaya pelanggan dibayar (jurnal
 * Pendapatan Garansi) → selesai. Semua transisi di bawah
 * lockForUpdate; stok hanya bergerak lewat InventoryService.
 */
class WarrantyService
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly FifoService $fifo,
        private readonly PostingService $posting,
        private readonly AuditLogger $audit,
    ) {}

    /** Cabang gudang utama: yang ditandai, atau cabang aktif tertua. */
    public function warehouseBranch(): Branch
    {
        return Branch::where('is_active', true)->where('is_main_warehouse', true)->orderBy('id')->first()
            ?? Branch::where('is_active', true)->orderBy('id')->firstOrFail();
    }

    public function createCase(User $actor, Sale $sale, SaleItem $item, int $quantity, string $reason, ?string $description): WarrantyCase
    {
        if (! $actor->hasPermission('sales.create')) {
            abort(403);
        }
        BranchContext::for($actor)->assertCanAccess($sale->branch_id);
        if ($item->sale_id !== $sale->id) {
            throw ValidationException::withMessages(['sale_item_id' => __('ui.warranty.item_mismatch')]);
        }
        if ($quantity < 1 || $quantity > $item->qty) {
            throw ValidationException::withMessages(['quantity' => __('ui.warranty.qty_exceeds')]);
        }

        return DB::transaction(function () use ($actor, $sale, $item, $quantity, $reason, $description) {
            SaleItem::query()->lockForUpdate()->findOrFail($item->id);
            $active = WarrantyCase::where('sale_item_id', $item->id)
                ->whereIn('status', WarrantyCase::ACTIVE_STATUSES)->exists();
            if ($active) {
                throw ValidationException::withMessages(['sale_item_id' => __('ui.warranty.case_active')]);
            }
            $case = WarrantyCase::create([
                'number' => Numbering::next('WR', now()),
                'sale_id' => $sale->id, 'sale_item_id' => $item->id, 'branch_id' => $sale->branch_id,
                'status' => 'open', 'reason' => $reason, 'description' => $description,
                'created_by' => $actor->id,
            ]);
            WarrantyReplacement::create([
                'warranty_case_id' => $case->id, 'original_sale_item_id' => $item->id,
                'source' => 'pending', 'quantity' => $quantity,
                'original_value' => Money::fromDecimal((string) $item->unit_price)->mul($quantity)->toDecimalString(),
                'status' => 'pending', 'created_by' => $actor->id,
            ]);
            $this->audit->log(action: 'warranty.create', subjectType: 'warranty_case', subjectId: $case->id,
                newValues: ['number' => $case->number, 'sale' => $sale->number], branchId: $sale->branch_id, actor: $actor);

            return $case;
        });
    }

    public function sendOldItem(User $actor, WarrantyCase $case): WarrantyCase
    {
        if (! $actor->hasPermission('sales.create')) {
            abort(403);
        }
        BranchContext::for($actor)->assertCanAccess($case->branch_id);

        return $this->transition($actor, $case, 'open', 'waiting_warehouse', 'warranty.send_old');
    }

    public function receiveOldItem(User $actor, WarrantyCase $case): BrokenStock
    {
        if (! $actor->hasPermission('stock.receive_transfer')) {
            abort(403);
        }
        $warehouse = $this->warehouseBranch();
        BranchContext::for($actor)->assertCanAccess($warehouse->id);

        return DB::transaction(function () use ($actor, $case, $warehouse) {
            $locked = WarrantyCase::query()->lockForUpdate()->findOrFail($case->id);
            if ($locked->status !== 'waiting_warehouse') {
                throw ValidationException::withMessages(['warranty' => __('ui.warranty.stage_invalid')]);
            }
            $replacement = $locked->replacement()->lockForUpdate()->firstOrFail();
            $item = SaleItem::findOrFail($locked->sale_item_id);
            $broken = BrokenStock::create([
                'branch_id' => $warehouse->id, 'product_id' => $item->product_id,
                'warranty_case_id' => $locked->id, 'quantity' => $replacement->quantity,
                'status' => 'pending_inspection', 'created_by' => $actor->id,
            ]);
            $this->audit->log(action: 'warranty.receive_old', subjectType: 'warranty_case', subjectId: $locked->id,
                newValues: ['broken_stock_id' => $broken->id], branchId: $warehouse->id, actor: $actor);

            return $broken;
        });
    }

    public function inspectBroken(User $actor, BrokenStock $broken, string $condition, string $reason, ?string $note): BrokenStock
    {
        if (! $actor->hasPermission('stock.receive_transfer')) {
            abort(403);
        }
        BranchContext::for($actor)->assertCanAccess($broken->branch_id);

        return DB::transaction(function () use ($actor, $broken, $condition, $reason, $note) {
            $locked = BrokenStock::query()->lockForUpdate()->findOrFail($broken->id);
            if ($locked->status !== 'pending_inspection') {
                throw ValidationException::withMessages(['broken_stock' => __('ui.warranty.already_inspected')]);
            }
            $case = WarrantyCase::query()->lockForUpdate()->findOrFail($locked->warranty_case_id);
            if ($condition === 'good') {
                $product = Product::findOrFail($locked->product_id);
                $sale = Sale::findOrFail($case->sale_id);
                $unitCost = $this->fifo->saleMovementUnitCost($sale->id, $product->id) ?? (string) $product->cost_price;
                $this->inventory->recordMovement($actor, $product, $locked->branch, 'warranty_return',
                    $locked->quantity, 'broken_stock', $locked->id, ['inboundUnitCost' => $unitCost]);
                $locked->update(['status' => 'resolved', 'resolution' => 'return_to_stock',
                    'reason' => $reason, 'note' => $note, 'resolved_by' => $actor->id, 'resolved_at' => now()]);
            } else {
                $locked->update(['status' => 'at_warehouse', 'resolution' => 'pending',
                    'reason' => $reason, 'note' => $note]);
            }
            $case->update(['status' => 'old_item_received']);
            $this->audit->log(action: 'warranty.inspect', subjectType: 'broken_stock', subjectId: $locked->id,
                newValues: ['condition' => $condition], branchId: $locked->branch_id, actor: $actor);

            return $locked;
        });
    }

    public function adjustBroken(User $actor, BrokenStock $broken, string $reason, string $note): BrokenStock
    {
        if (! $actor->hasPermission('stock.correct')) {
            abort(403);
        }
        BranchContext::for($actor)->assertCanAccess($broken->branch_id);

        return DB::transaction(function () use ($actor, $broken, $reason, $note) {
            $locked = BrokenStock::query()->lockForUpdate()->findOrFail($broken->id);
            if ($locked->status !== 'at_warehouse') {
                throw ValidationException::withMessages(['broken_stock' => __('ui.warranty.not_at_warehouse')]);
            }
            // Kuantitas rusak tidak pernah masuk stok jual: write-off
            // hanya menutup catatan stok rusak (jejak alasan wajib).
            $locked->update(['quantity' => 0, 'status' => 'resolved', 'resolution' => 'adjustment',
                'reason' => $reason, 'note' => $note, 'resolved_by' => $actor->id, 'resolved_at' => now()]);
            $this->audit->log(action: 'warranty.adjust_broken', subjectType: 'broken_stock', subjectId: $locked->id,
                newValues: ['reason' => $reason], branchId: $locked->branch_id, actor: $actor);

            return $locked;
        });
    }

    public function prepareReplacement(User $actor, WarrantyCase $case, string $source, Product $product, string $customerCharge): WarrantyCase
    {
        if (! $actor->hasPermission('stock.transfer')) {
            abort(403);
        }
        $warehouse = $this->warehouseBranch();
        BranchContext::for($actor)->assertCanAccess($warehouse->id);

        return DB::transaction(function () use ($actor, $case, $source, $product, $customerCharge, $warehouse) {
            $locked = WarrantyCase::query()->lockForUpdate()->findOrFail($case->id);
            if ($locked->status !== 'old_item_received') {
                throw ValidationException::withMessages(['warranty' => __('ui.warranty.stage_invalid')]);
            }
            $replacement = $locked->replacement()->lockForUpdate()->firstOrFail();
            if ($source === 'stock') {
                $available = $this->inventory->balancesForBranch($warehouse->id)[$product->id] ?? 0;
                if ($available < $replacement->quantity) {
                    throw ValidationException::withMessages(['product_id' => __('ui.warranty.stock_short')]);
                }
            }
            $replacement->update([
                'source' => $source, 'replacement_product_id' => $product->id,
                'replacement_value' => Money::fromDecimal((string) $product->sale_price)->mul($replacement->quantity)->toDecimalString(),
                'customer_charge' => Money::fromDecimal($customerCharge)->toDecimalString(),
                'status' => $source === 'stock' ? 'ready' : 'waiting_po',
            ]);
            $locked->update(['status' => 'replacement_preparing']);
            $this->audit->log(action: 'warranty.prepare', subjectType: 'warranty_case', subjectId: $locked->id,
                newValues: ['source' => $source], branchId: $warehouse->id, actor: $actor);

            return $locked;
        });
    }

    public function createReplacementPo(User $actor, WarrantyCase $case, Supplier $supplier): WarrantyCase
    {
        $warehouse = $this->warehouseBranch();

        return DB::transaction(function () use ($actor, $case, $supplier, $warehouse) {
            $locked = WarrantyCase::query()->lockForUpdate()->findOrFail($case->id);
            $replacement = $locked->replacement()->lockForUpdate()->firstOrFail();
            if ($replacement->source !== 'purchase_order' || $replacement->replacement_product_id === null) {
                throw ValidationException::withMessages(['warranty' => __('ui.warranty.not_po_source')]);
            }
            if ($replacement->purchase_order_id !== null) {
                throw ValidationException::withMessages(['warranty' => __('ui.warranty.po_exists')]);
            }
            $product = Product::findOrFail($replacement->replacement_product_id);
            $orders = app(PurchaseOrderService::class);
            $po = $orders->create($actor, $supplier->id, $warehouse->id, [
                ['product_id' => $product->id, 'qty' => $replacement->quantity, 'unit_cost' => (string) $product->cost_price],
            ]);
            $orders->markSent($actor, $po);
            $replacement->update(['purchase_order_id' => $po->id]);

            return $locked;
        });
    }

    /** Pengganti siap kirim? (stok langsung, atau PO-nya sudah diterima cukup.) */
    public function replacementReady(WarrantyCase $case): bool
    {
        $replacement = $case->replacement;
        if ($replacement === null) {
            return false;
        }
        if ($replacement->source === 'stock') {
            return $replacement->status === 'ready';
        }
        if ($replacement->source === 'purchase_order' && $replacement->purchase_order_id !== null) {
            $received = (int) DB::table('receipt_items')
                ->join('receipts', 'receipts.id', '=', 'receipt_items.receipt_id')
                ->where('receipts.purchase_order_id', $replacement->purchase_order_id)
                ->sum('receipt_items.qty');

            return $received >= $replacement->quantity;
        }

        return false;
    }

    public function shipReplacement(User $actor, WarrantyCase $case): WarrantyCase
    {
        if (! $actor->hasPermission('stock.transfer')) {
            abort(403);
        }
        $warehouse = $this->warehouseBranch();
        BranchContext::for($actor)->assertCanAccess($warehouse->id);

        return DB::transaction(function () use ($actor, $case, $warehouse) {
            $locked = WarrantyCase::query()->lockForUpdate()->findOrFail($case->id);
            if ($locked->status !== 'replacement_preparing' || ! $this->replacementReady($locked)) {
                throw ValidationException::withMessages(['warranty' => __('ui.warranty.not_ready')]);
            }
            $replacement = $locked->replacement()->lockForUpdate()->firstOrFail();
            $product = Product::findOrFail($replacement->replacement_product_id);
            $movement = $this->inventory->recordMovement($actor, $product, $warehouse, 'warranty_out',
                -$replacement->quantity, 'warranty_case', $locked->id);
            $replacement->update(['status' => 'shipped', 'shipped_movement_id' => $movement->id]);
            $locked->update(['status' => 'replacement_shipped']);
            $cost = Money::fromDecimal((string) ($movement->total_cost ?? '0'));
            if (! $cost->isZero()) {
                $this->posting->post('warranty_shipment', $locked->id, now()->toDateString(), [
                    ['account' => Chart::BEBAN_GARANSI, 'branch_id' => $warehouse->id, 'debit' => $cost->toDecimalString(), 'memo' => $locked->number],
                    ['account' => Chart::PERSEDIAAN, 'branch_id' => $warehouse->id, 'credit' => $cost->toDecimalString(), 'memo' => $locked->number],
                ], 'Beban garansi '.$locked->number, $actor);
            }
            $this->audit->log(action: 'warranty.ship', subjectType: 'warranty_case', subjectId: $locked->id,
                branchId: $warehouse->id, actor: $actor);

            return $locked;
        });
    }

    public function receiveReplacement(User $actor, WarrantyCase $case): WarrantyCase
    {
        if (! $actor->hasPermission('stock.receive_transfer')) {
            abort(403);
        }
        BranchContext::for($actor)->assertCanAccess($case->branch_id);

        return DB::transaction(function () use ($actor, $case) {
            $locked = WarrantyCase::query()->lockForUpdate()->findOrFail($case->id);
            if ($locked->status !== 'replacement_shipped') {
                throw ValidationException::withMessages(['warranty' => __('ui.warranty.stage_invalid')]);
            }
            $replacement = $locked->replacement()->lockForUpdate()->firstOrFail();
            $product = Product::findOrFail($replacement->replacement_product_id);
            $qty = $replacement->quantity;
            // Biaya per unit lapisan masuk = total biaya gerakan keluar
            // dibagi qty dalam sen bulat (Money); selisih pembulatan
            // lapisan maksimal qty-1 sen, jurnal tidak terpengaruh.
            $outMovement = $replacement->shipped_movement_id !== null
                ? StockMovement::find($replacement->shipped_movement_id) : null;
            $totalCents = $outMovement !== null && $outMovement->total_cost !== null
                ? Money::fromDecimal((string) $outMovement->total_cost)->cents() : 0;
            $perUnit = $qty > 0 ? Money::of(intdiv($totalCents, $qty))->toDecimalString() : '0';
            $this->inventory->recordMovement($actor, $product, $locked->branch, 'warranty_in',
                $qty, 'warranty_case', $locked->id, ['inboundUnitCost' => $perUnit]);
            $replacement->update(['status' => 'received']);
            $locked->update(['status' => 'replacement_received']);
            $this->audit->log(action: 'warranty.receive_replacement', subjectType: 'warranty_case', subjectId: $locked->id,
                branchId: $locked->branch_id, actor: $actor);

            return $locked;
        });
    }

    public function pay(User $actor, WarrantyCase $case, string $method, string $amount): WarrantyPayment
    {
        if (! $actor->hasPermission('sales.pay')) {
            abort(403);
        }
        BranchContext::for($actor)->assertCanAccess($case->branch_id);

        return DB::transaction(function () use ($actor, $case, $method, $amount) {
            $locked = WarrantyCase::query()->lockForUpdate()->findOrFail($case->id);
            $replacement = $locked->replacement()->lockForUpdate()->firstOrFail();
            $charge = Money::fromDecimal((string) $replacement->customer_charge);
            $paid = Money::fromDecimal($locked->paidAmount());
            $paying = Money::fromDecimal($amount);
            if ($paying->isZero() || $paying->isNegative() || $paid->add($paying)->cmp($charge) > 0) {
                throw ValidationException::withMessages(['amount' => __('ui.warranty.pay_exceeds')]);
            }
            $payment = WarrantyPayment::create([
                'warranty_case_id' => $locked->id, 'method' => $method,
                'amount' => $paying->toDecimalString(), 'payment_date' => now()->toDateString(),
                'created_by' => $actor->id,
            ]);
            $this->posting->post('warranty_payment', $payment->id, now()->toDateString(), [
                ['account' => Chart::tenderAccount($method), 'branch_id' => $locked->branch_id, 'debit' => $paying->toDecimalString(), 'memo' => $locked->number],
                ['account' => Chart::PENDAPATAN_GARANSI, 'branch_id' => $locked->branch_id, 'credit' => $paying->toDecimalString(), 'memo' => $locked->number],
            ], 'Pembayaran garansi '.$locked->number, $actor);
            $this->audit->log(action: 'warranty.pay', subjectType: 'warranty_case', subjectId: $locked->id,
                newValues: ['amount' => $paying->toDecimalString()], branchId: $locked->branch_id, actor: $actor);

            return $payment;
        });
    }

    public function complete(User $actor, WarrantyCase $case): WarrantyCase
    {
        if (! $actor->hasPermission('sales.create')) {
            abort(403);
        }
        BranchContext::for($actor)->assertCanAccess($case->branch_id);

        return DB::transaction(function () use ($actor, $case) {
            $locked = WarrantyCase::query()->lockForUpdate()->findOrFail($case->id);
            if ($locked->status !== 'replacement_received') {
                throw ValidationException::withMessages(['warranty' => __('ui.warranty.stage_invalid')]);
            }
            $replacement = $locked->replacement()->lockForUpdate()->firstOrFail();
            $charge = Money::fromDecimal((string) $replacement->customer_charge);
            if (Money::fromDecimal($locked->paidAmount())->cmp($charge) < 0) {
                throw ValidationException::withMessages(['warranty' => __('ui.warranty.charge_unpaid')]);
            }
            $locked->update(['status' => 'completed', 'completed_at' => now()]);
            $this->audit->log(action: 'warranty.complete', subjectType: 'warranty_case', subjectId: $locked->id,
                branchId: $locked->branch_id, actor: $actor);

            return $locked;
        });
    }

    private function transition(User $actor, WarrantyCase $case, string $from, string $to, string $auditAction): WarrantyCase
    {
        return DB::transaction(function () use ($actor, $case, $from, $to, $auditAction) {
            $locked = WarrantyCase::query()->lockForUpdate()->findOrFail($case->id);
            if ($locked->status !== $from) {
                throw ValidationException::withMessages(['warranty' => __('ui.warranty.stage_invalid')]);
            }
            $locked->update(['status' => $to]);
            $this->audit->log(action: $auditAction, subjectType: 'warranty_case', subjectId: $locked->id,
                branchId: $locked->branch_id, actor: $actor);

            return $locked;
        });
    }
}
