<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Catalog\Services\ProductService;
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

    /**
     * Sisa distribusi per detail dari satu PO: diterima − terkirim
     * aktif. Berkunci komposit "alias:id" (A1).
     *
     * @return array<string, int>
     */
    public function distributableFromPo(PurchaseOrder $po): array
    {
        $received = [];
        foreach ($po->items as $item) {
            $key = $item->detail_type.':'.$item->detail_id;
            $received[$key] = ($received[$key] ?? 0) + $item->qty_received;
        }
        $sentRows = DB::table('stock_transfer_items')
            ->join('stock_transfers', 'stock_transfers.id', '=', 'stock_transfer_items.stock_transfer_id')
            ->where('stock_transfers.source_type', 'purchase_order')
            ->where('stock_transfers.source_id', $po->id)
            ->whereIn('stock_transfers.status', ['sent', 'received'])
            ->groupBy('stock_transfer_items.detail_type', 'stock_transfer_items.detail_id')
            ->selectRaw('stock_transfer_items.detail_type, stock_transfer_items.detail_id, SUM(stock_transfer_items.qty) as total')
            ->get();
        $sent = [];
        foreach ($sentRows as $row) {
            $sent[$row->detail_type.':'.$row->detail_id] = (int) $row->total;
        }

        $result = [];
        foreach ($received as $key => $qty) {
            $result[$key] = $qty - (int) ($sent[$key] ?? 0);
        }

        return $result;
    }

    /**
     * @param  array<int, array{detail_type: string, detail_id: int, qty: int}>  $lines
     */
    public function sendFromPo(User $actor, PurchaseOrder $po, int $toBranchId, array $lines, ?string $notes = null): StockTransfer
    {
        return $this->send($actor, $po->branch_id, $toBranchId, $lines, 'purchase_order', $po->id, $notes);
    }

    /**
     * @param  array<int, array{detail_type: string, detail_id: int, qty: int}>  $lines
     */
    public function sendFromBranchStock(User $actor, int $fromBranchId, int $toBranchId, array $lines, ?string $notes = null): StockTransfer
    {
        return $this->send($actor, $fromBranchId, $toBranchId, $lines, 'branch_stock', null, $notes);
    }

    /**
     * @param  array<int, array{detail_type: string, detail_id: int, qty: int}>  $lines
     */
    private function send(User $actor, int $fromBranchId, int $toBranchId, array $lines, string $sourceType, ?int $sourceId, ?string $notes): StockTransfer
    {
        $context = BranchContext::for($actor);
        $context->assertCanAccess($fromBranchId);
        // Tujuan transfer WAJIB satu perusahaan dengan aktor: tanpa
        // pemeriksaan ini transfer dapat dibuat menuju cabang tenant
        // lain (penerimaan di sisi sana bahkan lolos, karena cabang
        // itu memang wilayah staf penerimanya).
        if (! in_array($toBranchId, $context->companyBranchIds(), true)) {
            throw ValidationException::withMessages(['to_branch_id' => __('ui.transfer.outside_company')]);
        }
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
                $key = $line['detail_type'].':'.(int) $line['detail_id'];
                $limit = $sourceType === 'purchase_order'
                    ? ($available[$key] ?? 0)
                    : $this->inventory->currentStock($line['detail_type'], (int) $line['detail_id'], $fromBranchId);
                if ((int) $line['qty'] > $limit) {
                    $detail = app(ProductService::class)->findDetail($line['detail_type'], (int) $line['detail_id']);
                    throw ValidationException::withMessages([
                        'items' => __('ui.transfer.exceeds_available', ['product' => $detail->product->name, 'available' => $limit]),
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
                $detail = app(ProductService::class)->findDetail($line['detail_type'], (int) $line['detail_id']);
                $transfer->items()->create(['detail_id' => $detail->id, 'detail_type' => $detail::DETAIL_TYPE, 'qty' => (int) $line['qty']]);
                $this->inventory->recordMovement(
                    $actor, $detail, $from, 'transfer_out',
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
            foreach ($locked->items()->with('detail')->get() as $item) {
                $this->inventory->recordMovement(
                    $actor, $item->detail, $to, 'transfer_in',
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
            foreach ($locked->items()->with('detail')->get() as $item) {
                // Gerakan penyeimbang: stok kembali masuk ke cabang asal.
                $this->inventory->recordMovement(
                    $actor, $item->detail, $from, 'transfer_in',
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
