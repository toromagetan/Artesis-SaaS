<?php

namespace App\Modules\Purchasing\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Platform\Services\AuditLogger;
use App\Modules\Purchasing\Events\PurchaseReturned;
use App\Modules\Purchasing\Models\PurchaseReturn;
use App\Modules\Purchasing\Models\Receipt;
use App\Support\Money;
use App\Support\Numbering;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Retur pembelian (Blueprint D.7 + PRD 6.6): stok keluar dari cabang
 * tujuan PO lewat InventoryService; nilai retur mengurangi sisa
 * tagihan receipt (reduces_payable) bila masih ada, atau menjadi
 * kredit supplier (supplier_credit) bila receipt sudah lunas.
 * Nilai per baris = qty × unit_cost_actual baris receipt (eksak,
 * tanpa prorata).
 */
class PurchaseReturnService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly InventoryService $inventory,
    ) {}

    /**
     * @param  array<int, array{receipt_item_id: int, qty: int}>  $lines
     */
    public function store(User $actor, Receipt $receipt, array $lines, string $reason): PurchaseReturn
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => __('ui.purchasing.return_reason_required')]);
        }

        return DB::transaction(function () use ($actor, $receipt, $lines, $reason) {
            $locked = Receipt::where('id', $receipt->id)
                ->with(['items.returnItems', 'allocations', 'returns', 'purchaseOrder'])
                ->lockForUpdate()
                ->firstOrFail();
            $branch = $locked->purchaseOrder->branch;

            $prepared = [];
            $total = Money::zero();
            foreach ($lines as $line) {
                $qty = (int) $line['qty'];
                if ($qty < 1) {
                    continue;
                }
                $item = $locked->items->firstWhere('id', (int) $line['receipt_item_id']);
                if ($item === null) {
                    throw ValidationException::withMessages(['items' => __('ui.purchasing.return_item_mismatch')]);
                }
                if ($qty > $item->qty - $item->returnedQty()) {
                    throw ValidationException::withMessages(['items' => __('ui.purchasing.return_qty_exceeds')]);
                }
                $amount = Money::fromDecimal((string) $item->unit_cost_actual)->mul($qty);
                $total = $total->add($amount);
                $prepared[] = ['item' => $item, 'qty' => $qty, 'amount' => $amount];
            }
            if ($prepared === []) {
                throw ValidationException::withMessages(['items' => __('ui.purchasing.items_required')]);
            }

            $creditStatus = $locked->remainingAmount()->isZero() ? 'supplier_credit' : 'reduces_payable';

            $retur = PurchaseReturn::create([
                'number' => Numbering::next('BR', now()),
                'receipt_id' => $locked->id,
                'reason' => $reason,
                'total_amount' => $total->toDecimalString(),
                'credit_status' => $creditStatus,
                'created_by' => $actor->id,
            ]);
            foreach ($prepared as $row) {
                $retur->items()->create([
                    'receipt_item_id' => $row['item']->id,
                    'qty' => $row['qty'],
                    'amount' => $row['amount']->toDecimalString(),
                ]);
                $product = $row['item']->purchaseOrderItem->product;
                $this->inventory->recordMovement(
                    $actor, $product, $branch, 'purchase_return',
                    -$row['qty'], 'purchase_return', $retur->id,
                    ['preferReceiptItemId' => $row['item']->id],
                );
            }

            $this->audit->log(action: 'purchase_return.create', subjectType: 'purchase_return', subjectId: $retur->id,
                newValues: ['number' => $retur->number, 'total' => $total->toDecimalString(), 'credit_status' => $creditStatus],
                branchId: $locked->purchaseOrder->branch_id, actor: $actor);

            PurchaseReturned::dispatch($retur);

            return $retur;
        });
    }
}
