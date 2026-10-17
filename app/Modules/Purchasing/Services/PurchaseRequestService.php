<?php

namespace App\Modules\Purchasing\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\BranchContext;
use App\Modules\Platform\Services\AuditLogger;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Models\PurchaseRequest;
use App\Support\Numbering;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Purchase Request (Blueprint D.7 + E.3): draft → submitted → approved
 * → converted; draft/submitted → cancelled. Konversi menandai item PR
 * dengan PO-nya; penggabungan multi-PR hanya untuk supplier & cabang
 * tujuan yang sama, dalam satu transaksi DB.
 */
class PurchaseRequestService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly PurchaseOrderService $orders,
    ) {}

    /**
     * @param  array<int, array{product_id: int, qty: int, estimated_price?: string|null, supplier_id?: int|null}>  $items
     */
    public function create(User $actor, int $branchId, array $items, ?string $notes = null): PurchaseRequest
    {
        BranchContext::for($actor)->assertCanAccess($branchId);
        if ($items === []) {
            throw ValidationException::withMessages(['items' => __('ui.purchasing.items_required')]);
        }

        return DB::transaction(function () use ($actor, $branchId, $items, $notes) {
            $pr = PurchaseRequest::create([
                'number' => Numbering::next('PR', now()),
                'requester_id' => $actor->id,
                'branch_id' => $branchId,
                'status' => 'draft',
                'notes' => $notes,
            ]);
            foreach ($items as $line) {
                if ((int) $line['qty'] < 1) {
                    throw ValidationException::withMessages(['items' => __('ui.purchasing.qty_positive')]);
                }
                $pr->items()->create([
                    'product_id' => (int) $line['product_id'],
                    'qty' => (int) $line['qty'],
                    'estimated_price' => $line['estimated_price'] ?? null,
                    'supplier_id' => $line['supplier_id'] ?? null,
                ]);
            }
            $this->audit->log(action: 'pr.create', subjectType: 'purchase_request', subjectId: $pr->id,
                newValues: ['number' => $pr->number], branchId: $branchId, actor: $actor);

            return $pr;
        });
    }

    public function submit(User $actor, PurchaseRequest $pr): PurchaseRequest
    {
        return $this->transition($actor, $pr, ['draft'], 'submitted', 'pr.submit');
    }

    public function approve(User $actor, PurchaseRequest $pr): PurchaseRequest
    {
        return $this->transition($actor, $pr, ['submitted'], 'approved', 'pr.approve');
    }

    public function cancel(User $actor, PurchaseRequest $pr): PurchaseRequest
    {
        return $this->transition($actor, $pr, ['draft', 'submitted'], 'cancelled', 'pr.cancel');
    }

    /**
     * Transisi status PR aman-konkurensi: baris dikunci ulang di
     * dalam transaksi sebelum status diperiksa — submit/approve/
     * cancel ganda serentak hanya boleh berhasil SATU kali
     * (gerbang konkurensi G.1.7).
     *
     * @param  string[]  $allowed
     */
    private function transition(User $actor, PurchaseRequest $pr, array $allowed, string $to, string $action): PurchaseRequest
    {
        return DB::transaction(function () use ($actor, $pr, $allowed, $to, $action) {
            $locked = PurchaseRequest::where('id', $pr->id)->lockForUpdate()->firstOrFail();
            $this->mustBe($locked, $allowed, $action);
            $locked->update(['status' => $to]);
            $this->audit->log(action: $action, subjectType: 'purchase_request', subjectId: $locked->id, branchId: $locked->branch_id, actor: $actor);
            // Cerminkan ke instance pemanggil (semantik in-place lama).
            $pr->forceFill($locked->getAttributes())->syncOriginal();

            return $locked;
        });
    }

    /**
     * Konversi satu/lebih PR approved menjadi SATU PO.
     * Semua PR wajib: status approved, cabang sama, dan setiap item
     * ber-usulan supplier sama dengan supplier tujuan (atau kosong).
     *
     * @param  PurchaseRequest[]  $prs
     */
    public function convertToPo(User $actor, array $prs, int $supplierId, ?string $orderDate = null): PurchaseOrder
    {
        if ($prs === []) {
            throw ValidationException::withMessages(['prs' => __('ui.purchasing.convert_empty')]);
        }

        return DB::transaction(function () use ($actor, $prs, $supplierId, $orderDate) {
            $branchId = null;
            $lines = [];
            foreach ($prs as $pr) {
                $locked = PurchaseRequest::where('id', $pr->id)->lockForUpdate()->firstOrFail();
                if ($locked->status !== 'approved') {
                    throw ValidationException::withMessages(['prs' => __('ui.purchasing.convert_not_approved', ['number' => $locked->number])]);
                }
                $branchId ??= $locked->branch_id;
                if ($locked->branch_id !== $branchId) {
                    throw ValidationException::withMessages(['prs' => __('ui.purchasing.convert_branch_mismatch')]);
                }
                foreach ($locked->items()->whereNull('purchase_order_id')->get() as $item) {
                    if ($item->supplier_id !== null && (int) $item->supplier_id !== $supplierId) {
                        throw ValidationException::withMessages(['prs' => __('ui.purchasing.convert_supplier_mismatch')]);
                    }
                    $lines[] = [
                        'product_id' => $item->product_id,
                        'qty' => $item->qty,
                        'unit_cost' => $item->estimated_price ?? $item->product->cost_price,
                        'pr_item_id' => $item->id,
                    ];
                }
            }
            if ($lines === []) {
                throw ValidationException::withMessages(['prs' => __('ui.purchasing.convert_empty')]);
            }

            $po = $this->orders->createFromLines($actor, $supplierId, (int) $branchId, $lines, $orderDate);

            $poId = $po->id;
            foreach ($prs as $pr) {
                $pr->items()->whereNull('purchase_order_id')->update(['purchase_order_id' => $poId]);
                $remaining = $pr->items()->whereNull('purchase_order_id')->count();
                if ($remaining === 0) {
                    $pr->update(['status' => 'converted']);
                }
            }
            $this->audit->log(action: 'pr.convert', subjectType: 'purchase_order', subjectId: $poId,
                newValues: ['from_pr' => collect($prs)->pluck('number')->all()], branchId: (int) $branchId, actor: $actor);

            return $po;
        });
    }

    /** @param string[] $allowed */
    private function mustBe(PurchaseRequest $pr, array $allowed, string $action): void
    {
        if (! in_array($pr->status, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => __('ui.purchasing.transition_invalid', ['action' => $action, 'status' => $pr->status]),
            ]);
        }
    }
}
