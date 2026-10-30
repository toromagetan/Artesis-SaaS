<?php

namespace App\Modules\Platform\Services;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use App\Modules\Platform\Models\Plan;
use App\Modules\Platform\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Pendaftaran mandiri TENANT (M5) — pendaftaran bisnis baru di level
 * platform, BUKAN registrasi pengguna di dalam tenant (yang tetap
 * hanya dibuat pengelola, sesuai aturan lama Lentera).
 *
 * Satu transaksi: Tenant + cabang pertama + pengguna pemilik
 * (peran Owner, terikat tenant & cabang) + langganan paket Free.
 */
class TenantSignupService
{
    public function __construct(private readonly PlanService $plans) {}

    /**
     * @param  array{business_name: string, owner_name: string, email: string, phone?: ?string, branch_name: string, password: string}  $data
     * @param  ?User  $actor  Aktor pencatat penetapan paket (panel platform:
     *                        admin platform; pendaftaran mandiri: pemilik
     *                        sendiri, sesuai perilaku semula).
     */
    public function register(array $data, ?User $actor = null): User
    {
        return DB::transaction(function () use ($data, $actor) {
            $tenant = Tenant::create([
                'name' => $data['business_name'],
                'slug' => $this->uniqueSlug($data['business_name']),
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'is_active' => true,
            ]);

            $branch = Branch::create([
                'name' => $data['branch_name'],
                'tenant_id' => $tenant->id,
            ]);

            $user = User::create([
                'name' => $data['owner_name'],
                'username' => $this->uniqueUsername($data['email']),
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => Hash::make($data['password']),
                'is_active' => true,
                'is_platform' => false,
                'tenant_id' => $tenant->id,
            ]);
            $user->roles()->attach(Role::where('name', 'Owner')->firstOrFail());
            $user->branches()->attach($branch);

            $free = Plan::where('key', 'free')->first();
            if ($free === null) {
                $this->plans->seedDefaultPlans();
                $free = Plan::where('key', 'free')->firstOrFail();
            }
            // Penetapan Free dicatat atas nama aktor (audit): pemilik
            // sendiri pada pendaftaran mandiri, admin platform dari panel.
            $this->plans->assignPlan($tenant, $free, null, $actor ?? $user);

            return $user;
        });
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'tenant';
        $slug = $base;
        $i = 2;
        while (Tenant::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    private function uniqueUsername(string $email): string
    {
        $base = Str::slug(Str::before($email, '@'), '_') ?: 'pemilik';
        $username = $base;
        $i = 2;
        while (User::where('username', $username)->exists()) {
            $username = $base.'_'.$i++;
        }

        return $username;
    }
}
