<?php

namespace App\Modules\Purchasing\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Platform\Services\AuditLogger;
use App\Modules\Purchasing\Events\ReceiptStored;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Models\Receipt;
use App\Support\Numbering;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Penerimaan barang (Blueprint D.7 + PRD 6.3): dokumen final saat
 * disimpan; stok masuk cabang tujuan PO pada transaksi yang sama;
 * qty_received denormalisasi terpelihara di sini. Penerimaan melebihi
 * sisa pesanan ditolak KECUALI pemanggil memegang izin pr.approve
 * (izin approve Pembelian) DAN memberi alasan — tercatat di audit.
 */
class ReceiptService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly InventoryService $inventory,
        private readonly PurchaseOrderService $orders,
    ) {}

    /**
     * @param  array<int, array{purchase_order_item_id: int, qty: int, unit_cost_actual?: string|null}>  $lines
     */
    public function store(
        User $actor,
        PurchaseOrder $po,
        array $lines,
        ?string $receivedDate = null,
        ?string $deliveryNoteNo = null,
        ?string $overReceiveReason = null,
    ): Receipt {
        if (! in_array($po->status, ['sent', 'partial'], true)) {
            throw ValidationException::withMessages(['status' => __('ui.purchasing.receipt_po_status', ['status' => $po->status])]);
        }
        if ($lines === []) {
            throw ValidationException::withMessages(['items' => __('ui.purchasing.items_required')]);
        }

        return DB::transaction(function () use ($actor, $po, $lines, $receivedDate, $deliveryNoteNo, $overReceiveReason) {
            $lockedPo = PurchaseOrder::where('id', $po->id)->lockForUpdate()->firstOrFail();
            $branch = $lockedPo->branch;

            // Validasi seluruh baris SEBELUM menulis apa pun.
            $prepared = [];
            $overReceiving = false;
            foreach ($lines as $line) {
                $qty = (int) $line['qty'];
                if ($qty < 1) {
                    continue;
                }
                $poItem = $lockedPo->items()->where('id', (int) $line['purchase_order_item_id'])->lockForUpdate()->first();
                if ($poItem === null) {
                    throw ValidationException::withMessages(['items' => __('ui.purchasing.receipt_item_mismatch')]);
                }
                if ($qty > $poItem->remainingQty()) {
                    $overReceiving = true;
                }
                $prepared[] = [
                    'item' => $poItem,
                    'qty' => $qty,
                    'unit_cost_actual' => $line['unit_cost_actual'] ?? $poItem->unit_cost,
                ];
            }
            if ($prepared === []) {
                throw ValidationException::withMessages(['items' => __('ui.purchasing.items_required')]);
            }
            if ($overReceiving) {
                if (! $actor->hasPermission('pr.approve') || trim((string) $overReceiveReason) === '') {
                    throw ValidationException::withMessages(['items' => __('ui.purchasing.over_receive_forbidden')]);
                }
            }

            $receipt = Receipt::create([
                'number' => Numbering::next('RCV', now()),
                'purchase_order_id' => $lockedPo->id,
                'received_date' => $receivedDate ?? now()->toDateString(),
                'delivery_note_no' => $deliveryNoteNo,
                'received_by' => $actor->id,
            ]);

            foreach ($prepared as $row) {
                $receipt->items()->create([
                    'purchase_order_item_id' => $row['item']->id,
                    'qty' => $row['qty'],
                    'unit_cost_actual' => $row['unit_cost_actual'],
                ]);
                $row['item']->increment('qty_received', $row['qty']);
                $this->inventory->recordMovement(
                    $actor, $row['item']->product, $branch, 'receipt',
                    $row['qty'], 'receipt', $receipt->id,
                );
            }

            $this->orders->refreshStatusFromReceipts($lockedPo);
            $this->audit->log(action: 'receipt.create', subjectType: 'receipt', subjectId: $receipt->id,
                newValues: [
                    'number' => $receipt->number,
                    'po' => $lockedPo->number,
                    'over_receiving' => $overReceiving,
                    'over_reason' => $overReceiving ? $overReceiveReason : null,
                ],
                branchId: $lockedPo->branch_id, actor: $actor);

            ReceiptStored::dispatch($receipt);

            return $receipt;
        });
    }
}
