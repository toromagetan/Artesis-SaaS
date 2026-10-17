<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\BranchContext;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Master\Models\Branch;
use App\Modules\Platform\Services\AuditLogger;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Support\Numbering;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Transfer distribusi (Blueprint D.8 + E.3 + PRD 7.3).
 *
 * - Dari PO: sisa yang dapat dikirim per produk = qty diterima PO −
 *   qty yang sudah ditransfer dari PO itu (status sent/received).
 * - Dari stok cabang: terbatas saldo asal untuk SEMUA tipe produk
 *   (transfer tidak pernah membuat stok asal negatif).
 * - sent menulis transfer_out di asal pada transaksi yang sama;
 *   received menulis transfer_in di tujuan — menerima dua kali
 *   DITOLAK (idempotensi dijaga status + lockForUpdate).
 * - cancelled hanya dari sent; stok asal kembali lewat gerakan
 *   penyeimbang transfer_in ber-reference dokumen yang sama.
 */
class TransferService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly InventoryService $inventory,
    ) {}

    /** Sisa distribusi per produk dari satu PO: diterima − terkirim aktif. */
    public function distributableFromPo(PurchaseOrder $po): array
    {
        $received = [];
        foreach ($po->items as $item) {
            $received[$item->product_id] = ($received[$item->product_id] ?? 0) + $item->qty_received;
        }
        $sent = DB::table('stock_transfer_items')
            ->join('stock_transfers', 'stock_transfers.id', '=', 'stock_transfer_items.stock_transfer_id')
            ->where('stock_transfers.source_type', 'purchase_order')
            ->where('stock_transfers.source_id', $po->id)
            ->whereIn('stock_transfers.status', ['sent', 'received'])
            ->groupBy('stock_transfer_items.product_id')
            ->selectRaw('stock_transfer_items.product_id, SUM(stock_transfer_items.qty) as total')
            ->pluck('total', 'product_id');

        $result = [];
        foreach ($received as $productId => $qty) {
            $result[$productId] = $qty - (int) ($sent[$productId] ?? 0);
        }

        return $result;
    }

    /**
     * @param  array<int, array{product_id: int, qty: int}>  $lines
     */
    public function sendFromPo(User $actor, PurchaseOrder $po, int $toBranchId, array $lines, ?string $notes = null): StockTransfer
    {
        return $this->send($actor, $po->branch_id, $toBranchId, $lines, 'purchase_order', $po->id, $notes);
    }

    /**
     * @param  array<int, array{product_id: int, qty: int}>  $lines
     */
    public function sendFromBranchStock(User $actor, int $fromBranchId, int $toBranchId, array $lines, ?string $notes = null): StockTransfer
    {
        return $this->send($actor, $fromBranchId, $toBranchId, $lines, 'branch_stock', null, $notes);
    }

    /**
     * @param  array<int, array{product_id: int, qty: int}>  $lines
     */
    private function send(User $actor, int $fromBranchId, int $toBranchId, array $lines, string $sourceType, ?int $sourceId, ?string $notes): StockTransfer
    {
        $context = BranchContext::for($actor);
        $context->assertCanAccess($fromBranchId);
        if ($fromBranchId === $toBranchId) {
            throw ValidationException::withMessages(['to_branch_id' => __('ui.transfer.same_branch')]);
        }
        $lines = array_values(array_filter($lines, fn ($row) => (int) ($row['qty'] ?? 0) > 0));
        if ($lines === []) {
            throw ValidationException::withMessages(['items' => __('ui.purchasing.items_required')]);
        }

        return DB::transaction(function () use ($actor, $fromBranchId, $toBranchId, $lines, $sourceType, $sourceId, $notes) {
            $from = Branch::findOrFail($fromBranchId);
            $to = Branch::findOrFail($toBranchId);

            // Batas ketersediaan per sumber.
            $available = [];
            if ($sourceType === 'purchase_order') {
                $po = PurchaseOrder::where('id', $sourceId)->lockForUpdate()->firstOrFail();
                $available = $this->distributableFromPo($po);
            }
            foreach ($lines as $line) {
                $productId = (int) $line['product_id'];
                $limit = $sourceType === 'purchase_order'
                    ? ($available[$productId] ?? 0)
                    : $this->inventory->currentStock($productId, $fromBranchId);
                if ((int) $line['qty'] > $limit) {
                    $product = Product::find($productId);
                    throw ValidationException::withMessages([
                        'items' => __('ui.transfer.exceeds_available', ['product' => $product?->name ?? '#'.$productId, 'available' => $limit]),
                    ]);
                }
            }

            $transfer = StockTransfer::create([
                'number' => Numbering::next('TRF', now()),
                'from_branch_id' => $fromBranchId,
                'to_branch_id' => $toBranchId,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'status' => 'sent',
                'sent_by' => $actor->id,
                'notes' => $notes,
            ]);
            foreach ($lines as $line) {
                $product = Product::findOrFail((int) $line['product_id']);
                $transfer->items()->create(['product_id' => $product->id, 'qty' => (int) $line['qty']]);
                $this->inventory->recordMovement(
                    $actor, $product, $from, 'transfer_out',
                    -(int) $line['qty'], 'stock_transfer', $transfer->id,
                );
            }
            $this->audit->log(action: 'transfer.send', subjectType: 'stock_transfer', subjectId: $transfer->id,
                newValues: ['number' => $transfer->number, 'to' => $to->name], branchId: $fromBranchId, actor: $actor);

            return $transfer;
        });
    }

    public function receive(User $actor, StockTransfer $transfer): StockTransfer
    {
        BranchContext::for($actor)->assertCanAccess($transfer->to_branch_id);

        return DB::transaction(function () use ($actor, $transfer) {
            $locked = StockTransfer::where('id', $transfer->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'sent') {
                throw ValidationException::withMessages(['status' => __('ui.transfer.receive_invalid', ['status' => $locked->status])]);
            }
            $to = Branch::findOrFail($locked->to_branch_id);
            foreach ($locked->items()->with('product')->get() as $item) {
                $this->inventory->recordMovement(
                    $actor, $item->product, $to, 'transfer_in',
                    $item->qty, 'stock_transfer', $locked->id,
                );
            }
            $locked->update(['status' => 'received', 'received_by' => $actor->id, 'received_at' => now()]);
            $this->audit->log(action: 'transfer.receive', subjectType: 'stock_transfer', subjectId: $locked->id,
                branchId: $locked->to_branch_id, actor: $actor);

            return $locked;
        });
    }

    public function cancel(User $actor, StockTransfer $transfer): StockTransfer
    {
        BranchContext::for($actor)->assertCanAccess($transfer->from_branch_id);

        return DB::transaction(function () use ($actor, $transfer) {
            $locked = StockTransfer::where('id', $transfer->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'sent') {
                throw ValidationException::withMessages(['status' => __('ui.transfer.cancel_invalid', ['status' => $locked->status])]);
            }
            $from = Branch::findOrFail($locked->from_branch_id);
            foreach ($locked->items()->with('product')->get() as $item) {
                // Gerakan penyeimbang: stok kembali masuk ke cabang asal.
                $this->inventory->recordMovement(
                    $actor, $item->product, $from, 'transfer_in',
                    $item->qty, 'stock_transfer', $locked->id,
                );
            }
            $locked->update(['status' => 'cancelled']);
            $this->audit->log(action: 'transfer.cancel', subjectType: 'stock_transfer', subjectId: $locked->id,
                branchId: $locked->from_branch_id, actor: $actor);

            return $locked;
        });
    }
}
