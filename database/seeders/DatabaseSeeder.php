<?php

namespace Database\Seeders;

use App\Modules\Identity\Services\TenantContext;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     * Fondasi: identitas & master (registry izin, peran, cabang, pengguna awal).
     */
    public function run(): void
    {
        // Seeder adalah proses sistem terpercaya tingkat instalasi:
        // konteks sistem dinyatakan eksplisit (audit lanjutan
        // temuan 8), bukan mengandalkan ketiadaan auth().
        TenantContext::asSystem(fn () => $this->call([
            IdentitySeeder::class,
            SaasSeeder::class,
            AccountingSeeder::class,
            QcPointTemplateSeeder::class,
            WaTemplateSeeder::class,
            MembershipLevelSeeder::class,
            ProductCategorySeeder::class,
        ]));
    }
}
