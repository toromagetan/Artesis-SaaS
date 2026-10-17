<?php

namespace App\Modules\Purchasing\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\BranchContext;
use App\Modules\Master\Models\Supplier;
use App\Modules\Platform\Services\AuditLogger;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Support\Money;
use App\Support\Numbering;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Purchase Order (Blueprint D.7 + E.3): draft → sent → partial →
 * complete; draft/sent → void ber-alasan (hanya bila belum ada
 * penerimaan sama sekali — sesudah ada penerimaan, koreksi lewat
 * retur pembelian). Status partial/complete dipelihara ReceiptService.
 */
class PurchaseOrderService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Buat PO manual (langsung, tanpa PR).
     *
     * @param  array<int, array{product_id: int, qty: int, unit_cost: string}>  $lines
     */
    public function create(User $actor, int $supplierId, int $branchId, array $lines, ?string $orderDate = null): PurchaseOrder
    {
        BranchContext::for($actor)->assertCanAccess($branchId);

        return DB::transaction(fn () => $this->createFromLines($actor, $supplierId, $branchId, $lines, $orderDate));
    }

    /**
     * Inti pembuatan PO (dipakai create() dan konversi PR — pemanggil
     * bertanggung jawab atas transaksi luarnya).
     *
     * @param  array<int, array{product_id: int, qty: int, unit_cost: string|null}>  $lines
     */
    public function createFromLines(User $actor, int $supplierId, int $branchId, array $lines, ?string $orderDate = null): PurchaseOrder
    {
        $supplier = Supplier::find($supplierId);
        if ($supplier === null || ! $supplier->is_active) {
            throw ValidationException::withMessages(['supplier_id' => __('ui.purchasing.supplier_inactive')]);
        }
        if ($lines === []) {
            throw ValidationException::withMessages(['items' => __('ui.purchasing.items_required')]);
        }

        $total = Money::zero();
        $prepared = [];
        foreach ($lines as $line) {
            $qty = (int) $line['qty'];
            if ($qty < 1) {
                throw ValidationException::withMessages(['items' => __('ui.purchasing.qty_positive')]);
            }
            if ($line['unit_cost'] === null || Money::fromDecimal((string) $line['unit_cost'])->isZero()) {
                throw ValidationException::withMessages(['items' => __('ui.purchasing.unit_cost_required')]);
            }
            $unitCost = Money::fromDecimal((string) $line['unit_cost']);
            $lineTotal = $unitCost->mul($qty);
            $total = $total->add($lineTotal);
            $prepared[] = [
                'product_id' => (int) $line['product_id'],
                'qty_ordered' => $qty,
                'qty_received' => 0,
                'unit_cost' => $unitCost->toDecimalString(),
                'line_total' => $lineTotal->toDecimalString(),
            ];
        }

        $po = PurchaseOrder::create([
            'number' => Numbering::next('PO', now()),
            'supplier_id' => $supplierId,
            'branch_id' => $branchId,
            'order_date' => $orderDate ?? now()->toDateString(),
            'total_amount' => $total->toDecimalString(),
            'status' => 'draft',
            'created_by' => $actor->id,
        ]);
        foreach ($prepared as $row) {
            $po->items()->create($row);
        }
        $this->audit->log(action: 'po.create', subjectType: 'purchase_order', subjectId: $po->id,
            newValues: ['number' => $po->number, 'total' => $total->toDecimalString()], branchId: $branchId, actor: $actor);

        return $po;
    }

    public function markSent(User $actor, PurchaseOrder $po): PurchaseOrder
    {
        if ($po->status !== 'draft') {
            throw ValidationException::withMessages(['status' => __('ui.purchasing.po_send_invalid', ['status' => $po->status])]);
        }
        $po->update(['status' => 'sent']);
        $this->audit->log(action: 'po.send', subjectType: 'purchase_order', subjectId: $po->id, branchId: $po->branch_id, actor: $actor);

        return $po;
    }

    public function void(User $actor, PurchaseOrder $po, string $reason): PurchaseOrder
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => __('ui.purchasing.void_reason_required')]);
        }
        if (! in_array($po->status, ['draft', 'sent'], true) || $po->receipts()->exists()) {
            throw ValidationException::withMessages(['status' => __('ui.purchasing.po_void_invalid')]);
        }
        $po->update(['status' => 'void', 'void_reason' => $reason]);
        $this->audit->log(action: 'po.void', subjectType: 'purchase_order', subjectId: $po->id,
            newValues: ['reason' => $reason], branchId: $po->branch_id, actor: $actor);

        return $po;
    }

    /** Status turunan dari kemajuan penerimaan (dipanggil ReceiptService). */
    public function refreshStatusFromReceipts(PurchaseOrder $po): void
    {
        if ($po->isVoid()) {
            return;
        }
        $items = $po->items()->get(['qty_ordered', 'qty_received']);
        $allFull = $items->every(fn ($item) => $item->qty_received >= $item->qty_ordered);
        $anyReceived = $items->contains(fn ($item) => $item->qty_received > 0);
        $po->update(['status' => $allFull ? 'complete' : ($anyReceived ? 'partial' : $po->status)]);
    }
}
