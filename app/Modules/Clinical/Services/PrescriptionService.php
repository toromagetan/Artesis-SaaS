<?php

namespace App\Modules\Clinical\Services;

use App\Modules\Clinical\Models\Prescription;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\BranchContext;
use App\Modules\Platform\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Layanan Resep (Blueprint D.5).
 * - AXIS wajib 0–180 pada mata yang CYL-nya ≠ 0.
 * - Resep terkunci begitu used_at terisi: update DITOLAK; perubahan
 *   dilakukan lewat resep baru (duplicate()).
 * - Kaitan pelanggan diambil dari konteks pemanggil (rute pelanggan),
 *   tidak pernah dari input bebas — pelajaran bug combobox pendahulu.
 */
class PrescriptionService
{
    /**
     * @param  array<string, mixed>  $data  nilai grid OD/OS + examined_at, examiner_id, prism, notes
     */
    public function create(User $actor, Customer $customer, array $data): Prescription
    {
        $this->assertAxisRules($data);

        return DB::transaction(function () use ($actor, $customer, $data) {
            $prescription = new Prescription([
                'examined_at' => $data['examined_at'],
                'examiner_id' => $data['examiner_id'] ?? null,
                'od_sph' => $data['od_sph'] ?? 0,
                'od_cyl' => $data['od_cyl'] ?? 0,
                'od_axis' => $data['od_axis'] ?? null,
                'od_add' => $data['od_add'] ?? null,
                'od_pdr' => $data['od_pdr'] ?? null,
                'os_sph' => $data['os_sph'] ?? 0,
                'os_cyl' => $data['os_cyl'] ?? 0,
                'os_axis' => $data['os_axis'] ?? null,
                'os_add' => $data['os_add'] ?? null,
                'os_pdl' => $data['os_pdl'] ?? null,
                'pd_total' => $data['pd_total'] ?? null,
                'prism' => $data['prism'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
            $prescription->customer_id = $customer->id;
            // branch_id = cabang pemeriksaan (atribut asal, ADR-012 —
            // resep sendiri GLOBAL mengikuti pelanggan): cabang aktif
            // aktor, atau cabang asal pelanggan bila aktor tak punya.
            $prescription->branch_id = BranchContext::for($actor)->currentBranchId() ?? $customer->branch_id;
            $prescription->save();

            app(AuditLogger::class)->log(
                action: 'prescriptions.create',
                subjectType: 'prescription',
                subjectId: $prescription->id,
                newValues: ['customer_id' => $customer->id, 'examined_at' => (string) $prescription->examined_at],
                branchId: $prescription->branch_id,
                actor: $actor,
            );

            return $prescription;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(User $actor, Prescription $prescription, array $data): Prescription
    {
        if ($prescription->isLocked()) {
            throw ValidationException::withMessages([
                'examined_at' => __('ui.prescriptions.locked'),
            ]);
        }

        $this->assertAxisRules($data);

        return DB::transaction(function () use ($actor, $prescription, $data) {
            $prescription->update([
                'examined_at' => $data['examined_at'] ?? $prescription->examined_at,
                'examiner_id' => $data['examiner_id'] ?? null,
                'od_sph' => $data['od_sph'] ?? 0,
                'od_cyl' => $data['od_cyl'] ?? 0,
                'od_axis' => $data['od_axis'] ?? null,
                'od_add' => $data['od_add'] ?? null,
                'od_pdr' => $data['od_pdr'] ?? null,
                'os_sph' => $data['os_sph'] ?? 0,
                'os_cyl' => $data['os_cyl'] ?? 0,
                'os_axis' => $data['os_axis'] ?? null,
                'os_add' => $data['os_add'] ?? null,
                'os_pdl' => $data['os_pdl'] ?? null,
                'pd_total' => $data['pd_total'] ?? null,
                'prism' => $data['prism'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            app(AuditLogger::class)->log(
                action: 'prescriptions.update',
                subjectType: 'prescription',
                subjectId: $prescription->id,
                branchId: $prescription->branch_id,
                actor: $actor,
            );

            return $prescription;
        });
    }

    /** Duplikat sebagai resep BARU (periksa ulang) — resep lama utuh. */
    public function duplicate(User $actor, Prescription $source): Prescription
    {
        return DB::transaction(function () use ($actor, $source) {
            $copy = $source->replicate(['used_at']);
            $copy->used_at = null;
            $copy->examined_at = now()->toDateString();
            // Periksa ulang terjadi di cabang aktif aktor (ADR-012).
            $copy->branch_id = BranchContext::for($actor)->currentBranchId() ?? $source->branch_id;
            $copy->save();

            app(AuditLogger::class)->log(
                action: 'prescriptions.duplicate',
                subjectType: 'prescription',
                subjectId: $copy->id,
                newValues: ['duplicated_from' => $source->id],
                branchId: $copy->branch_id,
                actor: $actor,
            );

            return $copy;
        });
    }

    /** Tandai terpakai transaksi — dipanggil Sales (M.3); idempoten. */
    public function markUsed(Prescription $prescription): void
    {
        if ($prescription->used_at === null) {
            $prescription->update(['used_at' => now()]);
        }
    }

    /** @param array<string, mixed> $data */
    private function assertAxisRules(array $data): void
    {
        $errors = [];
        foreach (['od', 'os'] as $side) {
            $cyl = ($data[$side.'_cyl'] ?? 0) + 0;
            $axis = $data[$side.'_axis'] ?? null;
            if ($cyl != 0) {
                if ($axis === null || $axis === '' || (int) $axis < 0 || (int) $axis > 180) {
                    $errors[$side.'_axis'] = __('ui.prescriptions.axis_required');
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
