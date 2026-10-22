<?php

namespace Database\Seeders;

use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Setting;
use App\Support\PermissionRegistry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Menanam registry izin (Bagian C.1), tujuh peran + matriks bawaannya (C.2),
 * cabang awal, pengaturan bawaan, serta pengguna Owner dan aktor Platform.
 *
 * Matriks C.2 diterjemahkan ke string izin di $matrix. Penanda pada C.2:
 * "izin" = TIDAK ditanam bawaan (hanya bila Owner memberi string-nya
 * secara eksplisit lewat UI Peran); "policy khusus" (impor stok awal)
 * hanya untuk Owner dan tetap dipagari policy di layer aplikasi.
 *
 * CATATAN KEAMANAN: kata sandi bawaan 'password' HANYA untuk lingkungan
 * pengembangan/uji. Instalasi produksi WAJIB menggantinya saat serah terima.
 */
class IdentitySeeder extends Seeder
{
    /** @var array<string, string[]> */
    private array $matrix = [
        'Owner' => ['*'],
        'Admin' => [
            'dashboard.view',
            'sales.view', 'sales.create', 'sales.update', 'sales.pay', 'sales.return',
            'customers.view', 'customers.create', 'customers.update', 'customers.deactivate',
            'prescriptions.view', 'prescriptions.create', 'prescriptions.print', 'medical_notes.view',
            'products.view', 'products.create', 'products.update', 'products.deactivate',
            'lab.view',
            'pr.view', 'pr.create', 'pr.approve', 'pr.cancel',
            'po.view', 'po.create', 'po.update', 'po.void', 'receipts.create', 'purchases.return',
            'stock.view', 'stock.transfer', 'stock.receive_transfer', 'stock.opname', 'stock.correct',
            'accounts.view', 'journals.view', 'reports.financial',
            'lab.templates.manage', 'sales.exceptions',
            'suppliers.manage', 'insurances.manage', 'doctors.manage',
            'wa.send', 'wa.inbox',
        ],
        'Kasir' => [
            'dashboard.view',
            'sales.view', 'sales.create', 'sales.update', 'sales.pay',
            'customers.view', 'customers.create', 'customers.update',
            'prescriptions.view',
            'products.view',
            'lab.view',
            'wa.send',
        ],
        'Gudang' => [
            'dashboard.view',
            'pr.view', 'pr.create',
            'po.view', 'po.create', 'po.update', 'receipts.create',
            'stock.view', 'stock.transfer', 'stock.receive_transfer', 'stock.opname', 'stock.correct',
            'products.view',
            'reports.stock',
        ],
        'Cabang' => [
            'dashboard.view',
            'sales.view', 'sales.create', 'sales.update', 'sales.pay',
            'customers.view', 'customers.create', 'customers.update',
            'prescriptions.view',
            'products.view',
            'lab.view',
            'pr.view', 'pr.create', 'receipts.create',
            'stock.view', 'stock.receive_transfer',
            'reports.sales', 'reports.stock',
            'wa.send',
        ],
        'Refraksionis' => [
            'dashboard.view',
            'prescriptions.view', 'prescriptions.create', 'prescriptions.print', 'medical_notes.view',
            'customers.view', 'customers.create',
        ],
        'Teknisi' => [
            'dashboard.view',
            'lab.view', 'lab.qc', 'lab.assign',
        ],
    ];

    public function run(): void
    {
        // 1) Permissions dari registry kanonis.
        $permissionIds = [];
        foreach (PermissionRegistry::groups() as $groupKey => $group) {
            foreach (array_keys($group['permissions']) as $name) {
                $permission = Permission::updateOrCreate(
                    ['name' => $name],
                    ['group_name' => $groupKey],
                );
                $permissionIds[$name] = $permission->id;
            }
        }

        // 2) Roles + matriks izin bawaan.
        $roleLabels = [
            'Owner' => 'Pemilik',
            'Admin' => 'Admin',
            'Kasir' => 'Kasir',
            'Gudang' => 'Gudang',
            'Cabang' => 'Cabang',
            'Refraksionis' => 'Refraksionis',
            'Teknisi' => 'Teknisi Lab',
        ];

        $roles = [];
        foreach ($roleLabels as $name => $label) {
            $role = Role::updateOrCreate(['name' => $name], ['label' => $label]);
            $grants = $this->matrix[$name] === ['*']
                ? PermissionRegistry::all()
                : $this->matrix[$name];
            foreach ($grants as $grant) {
                PermissionRegistry::assertExists($grant);
            }
            $role->permissions()->sync(array_map(fn ($g) => $permissionIds[$g], $grants));
            $roles[$name] = $role;
        }

        // 3) Cabang awal + pengaturan bawaan.
        Branch::updateOrCreate(['name' => 'Pusat'], ['is_active' => true]);

        $settings = [
            'store.name' => 'Lentera',
            'store.tagline' => 'Jernih Datanya, Terang Bisnisnya',
            'tax.rate_default' => '11',
            'tax.mode' => 'exclusive',
            'print.paper_default' => 'A5',
            'date.format' => 'd/m/Y',
        ];
        foreach ($settings as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        // 4) Pengguna awal: Owner (pelaku bisnis, akses global) dan
        //    aktor Platform (teknis, TANPA peran bisnis apa pun).
        $owner = User::updateOrCreate(
            ['username' => 'owner'],
            [
                'name' => 'Owner Lentera',
                'email' => 'owner@lentera.local',
                'password' => Hash::make('password'),
                'is_active' => true,
                'is_platform' => false,
            ],
        );
        $owner->roles()->sync([$roles['Owner']->id]);

        $platform = User::updateOrCreate(
            ['username' => 'platform'],
            [
                'name' => 'Platform Lentera',
                'email' => 'platform@lentera.local',
                'password' => Hash::make('password'),
                'is_active' => true,
                'is_platform' => true,
            ],
        );
        $platform->roles()->sync([]);
    }
}
