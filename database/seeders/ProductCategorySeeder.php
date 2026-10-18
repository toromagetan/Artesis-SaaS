<?php

namespace Database\Seeders;

use App\Modules\Catalog\Models\ProductCategory;
use Illuminate\Database\Seeder;

/**
 * Lima kategori produk bawaan (paritas Artesis) — terpetakan
 * 1:1 ke tipe sistem. Baris yang sama juga dibuat migrasi
 * 000035; seeder ini menjaga instalasi baru tetap lengkap.
 */
class ProductCategorySeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'frame' => ['Frame', 'Kacamata frame untuk berbagai jenis lensa'],
            'lens' => ['Lensa', 'Lensa siap pakai dengan ukuran standar'],
            'rx' => ['Lensa RX', 'Lensa resep sesuai kebutuhan mata pelanggan'],
            'softlens' => ['Softlens', 'Lensa kontak lunak dengan berbagai warna dan ukuran'],
            'accessory' => ['Aksesoris', 'Aksesoris pendukung seperti pembersih lensa, case, dan lainnya'],
        ];

        $order = 0;
        foreach ($defaults as $type => [$name, $description]) {
            ProductCategory::firstOrCreate(
                ['type' => $type, 'name' => $name],
                ['description' => $description, 'is_active' => true, 'sort_order' => $order],
            );
            $order++;
        }
    }
}
