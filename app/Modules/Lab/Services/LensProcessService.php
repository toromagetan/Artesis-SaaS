<?php

namespace App\Modules\Lab\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Lab\Events\LensProcessCompleted;
use App\Modules\Lab\Models\LensProcess;
use App\Modules\Platform\Services\AuditLogger;
use App\Modules\Sales\Models\Sale;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Layanan Proses Lensa (Blueprint D.6/D.7 + E.3).
 *
 * Mesin status: waiting → edging → qc → ready → done. Lompatan dan
 * langkah mundur ditolak. Status done HANYA lewat complete() yang
 * menulis completed_at TEPAT SEKALI — pemanggilan kedua ditolak
 * ValidationException (di bawah lockForUpdate, complete ganda yang
 * bersamaan terserialisasi: tercatat satu kali).
 */
class LensProcessService
{
    /** Tipe item penjualan yang membutuhkan proses lab. */
    private const LAB_TYPES = ['lens', 'rx'];

    private const NEXT = [
        'waiting' => 'edging',
        'edging' => 'qc',
        'qc' => 'ready',
    ];

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly QcCheckService $qc,
    ) {}

    private function branchIdOf(LensProcess $process): ?int
    {
        $branchId = $process->sale()->withoutGlobalScopes()->value('branch_id');

        return $branchId !== null ? (int) $branchId : null;
    }

    /**
     * Buat proses untuk penjualan yang memuat item lensa (idempoten —
     * sale_id UNIQUE + firstOrCreate). Dipanggil listener peristiwa
     * SaleCompleted di dalam transaksi penjualan.
     */
    public function ensureForSale(Sale $sale): ?LensProcess
    {
        $hasLabItem = $sale->items()
            ->whereIn('type', self::LAB_TYPES)
            ->exists();
        if (! $hasLabItem) {
            return null;
        }

        return LensProcess::firstOrCreate(
            ['sale_id' => $sale->id],
            ['status' => 'waiting', 'started_at' => now()],
        );
    }

    /** Antrean void: proses yang belum done dibuang bersama transaksinya. */
    public function removeForVoidedSale(Sale $sale): void
    {
        LensProcess::where('sale_id', $sale->id)
            ->where('status', '!=', 'done')
            ->delete();
    }

    public function assignTechnician(User $actor, LensProcess $process, int $technicianId, ?int $estimatedDays = null): LensProcess
    {
        if ($process->isDone()) {
            throw ValidationException::withMessages(['status' => __('ui.lab.already_done')]);
        }

        $technician = User::where('id', $technicianId)
            ->where('is_active', true)
            ->where('is_platform', false)
            ->first();
        if ($technician === null) {
            throw ValidationException::withMessages(['technician_id' => __('ui.lab.technician_invalid')]);
        }

        $process->update([
            'technician_id' => $technician->id,
            'estimated_days' => $estimatedDays,
        ]);
        $this->audit->log(
            action: 'lab.assign',
            subjectType: 'lens_process',
            subjectId: $process->id,
            newValues: ['technician_id' => $technician->id, 'estimated_days' => $estimatedDays],
            branchId: $this->branchIdOf($process),
            actor: $actor,
        );

        return $process;
    }

    /** Maju satu langkah pada rantai waiting→edging→qc→ready. */
    public function transition(User $actor, LensProcess $process, string $to): LensProcess
    {
        $expected = self::NEXT[$process->status] ?? null;
        if ($expected === null || $to !== $expected) {
            throw ValidationException::withMessages([
                'status' => __('ui.lab.transition_invalid', ['from' => $process->status, 'to' => $to]),
            ]);
        }

        $process->update(['status' => $to]);
        $this->audit->log(
            action: 'lab.transition',
            subjectType: 'lens_process',
            subjectId: $process->id,
            newValues: ['to' => $to],
            branchId: $this->branchIdOf($process),
            actor: $actor,
        );

        return $process;
    }

    /**
     * Selesaikan proses: menulis completed_at tepat sekali.
     * Pemanggilan pada proses yang sudah done DITOLAK.
     */
    public function complete(User $actor, LensProcess $process): LensProcess
    {
        return DB::transaction(function () use ($actor, $process) {
            $locked = LensProcess::where('id', $process->id)->lockForUpdate()->firstOrFail();
            if ($locked->isDone()) {
                throw ValidationException::withMessages(['status' => __('ui.lab.already_done')]);
            }

            // Gerbang QC (F2.2): semua titik wajib yang aktif harus Lulus
            // pada percobaan terakhirnya sebelum proses diselesaikan.
            $this->qc->assertCompletable($locked);

            $locked->update(['status' => 'done', 'completed_at' => now()]);
            $this->audit->log(
                action: 'lab.complete',
                subjectType: 'lens_process',
                subjectId: $locked->id,
                newValues: ['duration_days' => $locked->durationDays()],
                branchId: $this->branchIdOf($locked),
                actor: $actor,
            );
            LensProcessCompleted::dispatch($locked);

            return $locked;
        });
    }
}
