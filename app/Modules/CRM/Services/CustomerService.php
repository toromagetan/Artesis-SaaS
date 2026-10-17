<?php

namespace App\Modules\CRM\Services;

use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\BranchContext;
use App\Modules\Platform\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Layanan Pelanggan (Blueprint D.4).
 * - Nomor WA duplikat memberi PERINGATAN, bukan blokir (keluarga dapat
 *   berbagi nomor) — dikembalikan sebagai flag 'phone_shared'.
 * - Penonaktifan saja; tidak ada penghapusan.
 */
class CustomerService
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{customer: Customer, phone_shared: bool}
     */
    public function create(User $actor, array $data): array
    {
        return DB::transaction(function () use ($actor, $data) {
            $phoneShared = $this->phoneSharedByOther($data['phone'], null);

            $customer = new Customer([
                'name' => $data['name'],
                'phone' => $data['phone'],
                'email' => $data['email'] ?? null,
                'address' => $data['address'] ?? null,
                'birth_date' => $data['birth_date'] ?? null,
                'insurance_id' => $data['insurance_id'] ?? null,
                'is_active' => true,
            ]);
            // BranchScoped mengisi branch_id dari konteks aktor; nilai
            // eksplisit dari formulir hanya diterima bila aktor berhak
            // atas cabang itu (assertCanAccess di model event tidak
            // menimpa nilai yang sudah terisi dan sah).
            if (! empty($data['branch_id'])) {
                BranchContext::for($actor)->assertCanAccess((int) $data['branch_id']);
                $customer->branch_id = (int) $data['branch_id'];
            }
            $customer->save();

            app(AuditLogger::class)->log(
                action: 'customers.create',
                subjectType: 'customer',
                subjectId: $customer->id,
                newValues: ['name' => $customer->name, 'phone' => $customer->phone],
                branchId: $customer->branch_id,
                actor: $actor,
            );

            return ['customer' => $customer, 'phone_shared' => $phoneShared];
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{customer: Customer, phone_shared: bool}
     */
    public function update(User $actor, Customer $customer, array $data): array
    {
        return DB::transaction(function () use ($actor, $customer, $data) {
            $phoneShared = $this->phoneSharedByOther($data['phone'] ?? $customer->phone, $customer->id);

            $old = ['name' => $customer->name, 'phone' => $customer->phone];
            $customer->update([
                'name' => $data['name'] ?? $customer->name,
                'phone' => $data['phone'] ?? $customer->phone,
                'email' => $data['email'] ?? null,
                'address' => $data['address'] ?? null,
                'birth_date' => $data['birth_date'] ?? null,
                'insurance_id' => $data['insurance_id'] ?? null,
            ]);

            app(AuditLogger::class)->log(
                action: 'customers.update',
                subjectType: 'customer',
                subjectId: $customer->id,
                oldValues: $old,
                newValues: ['name' => $customer->name, 'phone' => $customer->phone],
                branchId: $customer->branch_id,
                actor: $actor,
            );

            return ['customer' => $customer, 'phone_shared' => $phoneShared];
        });
    }

    public function setActive(User $actor, Customer $customer, bool $active): Customer
    {
        $customer->update(['is_active' => $active]);

        app(AuditLogger::class)->log(
            action: $active ? 'customers.activate' : 'customers.deactivate',
            subjectType: 'customer',
            subjectId: $customer->id,
            newValues: ['is_active' => $active],
            branchId: $customer->branch_id,
            actor: $actor,
        );

        return $customer;
    }

    private function phoneSharedByOther(string $phone, ?int $exceptId): bool
    {
        return Customer::withoutGlobalScopes()
            ->where('phone', $phone)
            ->where('is_active', true)
            ->when($exceptId !== null, fn ($q) => $q->where('id', '!=', $exceptId))
            ->exists();
    }
}
