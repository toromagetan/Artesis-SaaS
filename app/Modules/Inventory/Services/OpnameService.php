<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\BranchContext;
use App\Modules\Inventory\Events\OpnameApproved;
use App\Modules\Inventory\Models\StockOpname;
use App\Modules\Master\Models\Branch;
use App\Modules\Platform\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Opname terjadwal (Blueprint B.4/D.8 + PRD-Detail 7.4, F4.1).
 *
 * Alur: scheduled → counting (snapshot system_qty per produk) →
 * review (semua baris terhitung) → approved (gerakan selisih
 * 'opname' terbit lewat InventoryService; jurnal dari listener
 * Accounting atas OpnameApproved). counting → cancelled hanya
 * Owner. Selama counting/review, mutasi stok cabang dikunci untuk
 * non-Owner — ditegakkan di InventoryService::recordMovement,
 * satu-satunya penulis gerakan.
 */
class OpnameService
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly AuditLogger $audit,
    ) {}

    public function schedule(User $actor, int $branchId, string $scheduledDate, ?string $notes = null): StockOpname
    {
        BranchContext::for($actor)->assertCanAccess($branchId);

        $exists = StockOpname::withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->whereIn('status', StockOpname::LOCKING_STATUSES)
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages(['branch_id' => __('ui.opname.already_active')]);
        }

        $opname = StockOpname::create([
            'branch_id' => $branchId,
            'scheduled_date' => $scheduledDate,
            'status' => 'scheduled',
            'created_by' => $actor->id,
            'notes' => $notes,
        ]);
        $this->audit->log(action: 'opname.schedule', subjectType: 'stock_opname', subjectId: $opname->id,
            newValues: ['branch' => Branch::find($branchId)?->name, 'date' => $scheduledDate],
            branchId: $branchId, actor: $actor);

        return $opname;
    }

    /** Mulai hitung: snapshot saldo resmi cabang menjadi system_qty. */
    public function startCounting(User $actor, StockOpname $opname): StockOpname
    {
        BranchContext::for($actor)->assertCanAccess($opname->branch_id);

        return DB::transaction(function () use ($actor, $opname) {
            $locked = StockOpname::where('id', $opname->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'scheduled') {
                throw ValidationException::withMessages(['status' => __('ui.opname.start_invalid', ['status' => $locked->status])]);
            }
            $otherActive = StockOpname::withoutGlobalScopes()
                ->where('branch_id', $locked->branch_id)
                ->where('id', '!=', $locked->id)
                ->whereIn('status', StockOpname::LOCKING_STATUSES)
                ->exists();
            if ($otherActive) {
                throw ValidationException::withMessages(['branch_id' => __('ui.opname.already_active')]);
            }
            $balances = $this->inventory->balancesForBranch($locked->branch_id);
            foreach ($balances as $productId => $balance) {
                $locked->items()->create([
                    'product_id' => $productId,
                    'system_qty' => $balance,
                    'counted_qty' => null,
                    'difference' => 0,
                ]);
            }
            $locked->update(['status' => 'counting']);
            $this->audit->log(action: 'opname.start', subjectType: 'stock_opname', subjectId: $locked->id,
                newValues: ['items' => count($balances)], branchId: $locked->branch_id, actor: $actor);

            return $locked;
        });
    }

    /**
     * Simpan hasil hitung fisik. $counts: [opname_item_id => qty].
     * Baris di luar lembar ini ditolak; qty negatif ditolak.
     */
    public function saveCounts(User $actor, StockOpname $opname, array $counts): StockOpname
    {
        BranchContext::for($actor)->assertCanAccess($opname->branch_id);

        return DB::transaction(function () use ($actor, $opname, $counts) {
            $locked = StockOpname::where('id', $opname->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'counting') {
                throw ValidationException::withMessages(['status' => __('ui.opname.count_invalid', ['status' => $locked->status])]);
            }
            $items = $locked->items()->lockForUpdate()->get()->keyBy('id');
            foreach ($counts as $itemId => $qty) {
                $item = $items->get((int) $itemId);
                if ($item === null) {
                    throw ValidationException::withMessages(['counts' => __('ui.opname.item_foreign')]);
                }
                if ($qty === null || $qty === '') {
                    $item->update(['counted_qty' => null, 'difference' => 0]);

                    continue;
                }
                if (! is_numeric($qty) || (int) $qty < 0) {
                    throw ValidationException::withMessages(['counts' => __('ui.opname.count_negative')]);
                }
                $counted = (int) $qty;
                $item->update([
                    'counted_qty' => $counted,
                    'difference' => $counted - $item->system_qty,
                ]);
            }
            $this->audit->log(action: 'opname.count', subjectType: 'stock_opname', subjectId: $locked->id,
                newValues: ['filled' => $locked->items()->whereNotNull('counted_qty')->count()],
                branchId: $locked->branch_id, actor: $actor);

            return $locked;
        });
    }

    /** Kirim ke review: semua baris wajib sudah terhitung. */
    public function submitReview(User $actor, StockOpname $opname): StockOpname
    {
        BranchContext::for($actor)->assertCanAccess($opname->branch_id);

        return DB::transaction(function () use ($opname) {
            $locked = StockOpname::where('id', $opname->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'counting') {
                throw ValidationException::withMessages(['status' => __('ui.opname.review_invalid', ['status' => $locked->status])]);
            }
            if ($locked->items()->whereNull('counted_qty')->exists()) {
                throw ValidationException::withMessages(['status' => __('ui.opname.review_incomplete')]);
            }
            $locked->update(['status' => 'review']);

            return $locked;
        });
    }

    /**
     * Setujui: status beralih ke approved DULU (di transaksi yang
     * sama) agar kunci cabang terangkat untuk gerakan selisihnya
     * sendiri, lalu gerakan 'opname' terbit per baris berselisih.
     */
    public function approve(User $actor, StockOpname $opname): StockOpname
    {
        BranchContext::for($actor)->assertCanAccess($opname->branch_id);

        return DB::transaction(function () use ($actor, $opname) {
            $locked = StockOpname::where('id', $opname->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'review') {
                throw ValidationException::withMessages(['status' => __('ui.opname.approve_invalid', ['status' => $locked->status])]);
            }
            $branch = Branch::findOrFail($locked->branch_id);
            $locked->update([
                'status' => 'approved',
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ]);
            foreach ($locked->items()->with('product')->get() as $item) {
                if ($item->difference === 0) {
                    continue;
                }
                $movement = $this->inventory->recordMovement(
                    $actor, $item->product, $branch, 'opname',
                    $item->difference, 'stock_opname', $locked->id,
                );
                $item->update(['movement_id' => $movement->id]);
            }
            $this->audit->log(action: 'opname.approve', subjectType: 'stock_opname', subjectId: $locked->id,
                newValues: ['differences' => $locked->items()->where('difference', '!=', 0)->count()],
                branchId: $locked->branch_id, actor: $actor);

            OpnameApproved::dispatch($locked);

            return $locked;
        });
    }

    /** Batalkan dari counting — HANYA Owner (Blueprint B.4). */
    public function cancel(User $actor, StockOpname $opname): StockOpname
    {
        if (! $actor->hasRole('Owner')) {
            throw ValidationException::withMessages(['status' => __('ui.opname.cancel_owner_only')]);
        }
        BranchContext::for($actor)->assertCanAccess($opname->branch_id);

        return DB::transaction(function () use ($actor, $opname) {
            $locked = StockOpname::where('id', $opname->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'counting') {
                throw ValidationException::withMessages(['status' => __('ui.opname.cancel_invalid', ['status' => $locked->status])]);
            }
            $locked->update(['status' => 'cancelled']);
            $this->audit->log(action: 'opname.cancel', subjectType: 'stock_opname', subjectId: $locked->id,
                branchId: $locked->branch_id, actor: $actor);

            return $locked;
        });
    }
}
