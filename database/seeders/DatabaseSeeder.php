<?php

namespace Database\Seeders;

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
        $this->call([
            IdentitySeeder::class,
            AccountingSeeder::class,
            QcPointTemplateSeeder::class,
        ]);
    }
}
