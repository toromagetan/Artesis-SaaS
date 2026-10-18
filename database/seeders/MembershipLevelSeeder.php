<?php

namespace Database\Seeders;

use App\Modules\Membership\Models\MembershipLevel;
use Illuminate\Database\Seeder;

/**
 * Level membership bawaan (F3.6). firstOrCreate per nama —
 * penanaman ulang tidak menimpa penyesuaian Admin.
 */
class MembershipLevelSeeder extends Seeder
{
    public function run(): void
    {
        $levels = [
            ['name' => 'Reguler', 'min_accumulation' => '0', 'point_multiplier' => '1.00', 'benefits' => 'Perolehan poin standar.'],
            ['name' => 'Silver', 'min_accumulation' => '5000000', 'point_multiplier' => '1.25', 'benefits' => 'Perolehan poin 1,25x.'],
            ['name' => 'Gold', 'min_accumulation' => '15000000', 'point_multiplier' => '1.50', 'benefits' => 'Perolehan poin 1,5x dan prioritas layanan.'],
        ];

        foreach ($levels as $level) {
            MembershipLevel::firstOrCreate(['name' => $level['name']], $level);
        }
    }
}
