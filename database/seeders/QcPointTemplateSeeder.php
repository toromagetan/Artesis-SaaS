<?php

namespace Database\Seeders;

use App\Modules\Lab\Models\QcPointTemplate;
use Illuminate\Database\Seeder;

/**
 * F2.1 — 13 titik QC faset bawaan (PRD Detail 5.2). firstOrCreate per
 * nama: penanaman ulang TIDAK menimpa penyesuaian Admin (urutan,
 * wajib/tidak, aktif) yang sudah dilakukan lewat UI kelola.
 */
class QcPointTemplateSeeder extends Seeder
{
    /** @var list<string> */
    private const POINTS = [
        'Kesesuaian ukuran resep dengan order (SPH/CYL)',
        'Centring & PD sesuai resep',
        'Axis sesuai resep (toleransi ±2°)',
        'Ukuran lensa sesuai bingkai frame',
        'Bentuk & simetri pasangan lensa',
        'Ketebalan tepi merata & aman',
        'Kerapian edging (tanpa gerigi / chip)',
        'Bevel tepat, lensa duduk stabil di rim',
        'Bebas goresan pada permukaan lensa',
        'Coating utuh tanpa cacat',
        'Kebersihan lensa & frame',
        'Kesejajaran & alignment frame',
        'Pemeriksaan visual akhir menyeluruh',
    ];

    public function run(): void
    {
        foreach (self::POINTS as $i => $name) {
            QcPointTemplate::firstOrCreate(
                ['name' => $name],
                ['is_required' => true, 'sort_order' => $i + 1, 'is_active' => true],
            );
        }
    }
}
