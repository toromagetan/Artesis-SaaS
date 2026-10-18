<?php

namespace App\Modules\Lab\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Lab\Models\LensProcess;
use App\Modules\Lab\Models\QcCheck;
use App\Modules\Lab\Models\QcPointTemplate;
use App\Modules\Platform\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Perekaman QC faset (F2.2, Blueprint B.6/D + PRD Detail 5.2).
 *
 * Aturan kunci:
 * - Satu catatan = satu percobaan; nomor attempt naik per (proses,
 *   titik) dan riwayat tidak pernah ditimpa.
 * - Hasil fail WAJIB ber-catatan.
 * - Titik nonaktif tidak dapat diperiksa; proses done terkunci.
 * - Complete digerbangi assertCompletable(): semua titik WAJIB yang
 *   AKTIF harus berstatus Lulus pada percobaan terakhirnya. Bila tidak
 *   ada titik wajib aktif, gerbang terbuka (Blueprint E.3).
 */
class QcCheckService
{
    public function __construct(private readonly AuditLogger $audit) {}

    private function branchIdOf(LensProcess $process): ?int
    {
        $branchId = $process->sale()->withoutGlobalScopes()->value('branch_id');

        return $branchId !== null ? (int) $branchId : null;
    }

    public function record(User $actor, LensProcess $process, int $pointId, string $result, ?string $notes = null): QcCheck
    {
        if (! $actor->hasPermission('lab.qc')) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        return DB::transaction(function () use ($actor, $process, $pointId, $result, $notes) {
            $locked = LensProcess::where('id', $process->id)->lockForUpdate()->firstOrFail();
            if ($locked->isDone()) {
                throw ValidationException::withMessages(['process' => __('ui.qc.process_done')]);
            }

            $point = QcPointTemplate::findOrFail($pointId);
            if (! $point->is_active) {
                throw ValidationException::withMessages(['point' => __('ui.qc.point_inactive')]);
            }
            if (! in_array($result, ['pass', 'fail'], true)) {
                throw ValidationException::withMessages(['result' => __('ui.qc.incomplete')]);
            }
            $notes = $notes !== null ? trim($notes) : null;
            if ($result === 'fail' && ($notes === null || $notes === '')) {
                throw ValidationException::withMessages(['notes' => __('ui.qc.notes_required_on_fail')]);
            }

            $attempt = (int) QcCheck::where('lens_process_id', $locked->id)
                ->where('qc_point_template_id', $point->id)
                ->max('attempt') + 1;

            $check = QcCheck::create([
                'lens_process_id' => $locked->id,
                'qc_point_template_id' => $point->id,
                'attempt' => $attempt,
                'result' => $result,
                'notes' => $notes === '' ? null : $notes,
                'checked_by' => $actor->id,
                'checked_at' => now(),
            ]);

            $this->audit->log(
                action: 'lab.qc_record',
                subjectType: 'lens_process',
                subjectId: $locked->id,
                newValues: ['point' => $point->name, 'attempt' => $attempt, 'result' => $result],
                branchId: $this->branchIdOf($locked),
                actor: $actor,
            );

            return $check;
        });
    }

    /** @return array<int, QcCheck> Peta point_id → pemeriksaan terakhir. */
    public function latestByPoint(LensProcess $process): array
    {
        return QcCheck::where('lens_process_id', $process->id)
            ->orderByDesc('attempt')
            ->orderByDesc('id')
            ->get()
            ->unique('qc_point_template_id')
            ->keyBy('qc_point_template_id')
            ->all();
    }

    /**
     * Titik wajib AKTIF yang percobaan terakhirnya bukan Lulus
     * (termasuk yang belum pernah diperiksa).
     *
     * @return Collection<int, QcPointTemplate>
     */
    public function missingRequired(LensProcess $process): Collection
    {
        $latest = $this->latestByPoint($process);

        return QcPointTemplate::activeOrdered()
            ->where('is_required', true)
            ->get()
            ->filter(function (QcPointTemplate $point) use ($latest) {
                $check = $latest[$point->id] ?? null;

                return $check === null || $check->result !== 'pass';
            })
            ->values();
    }

    /** Gerbang Complete (F2.2): ditolak selama titik wajib belum Lulus. */
    public function assertCompletable(LensProcess $process): void
    {
        if ($this->missingRequired($process)->isNotEmpty()) {
            throw ValidationException::withMessages(['qc' => __('ui.qc.incomplete')]);
        }
    }
}
