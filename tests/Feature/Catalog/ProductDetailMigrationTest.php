<?php

namespace Tests\Feature\Catalog;

use App\Modules\Master\Models\Branch;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Sales\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * A1 — migrasi 000060 adopsi detail Artesis: pemetaan data bentuk
 * lama (induk + varian) ke bentuk baru (master + detail per tipe)
 * diverifikasi baris demi baris, termasuk rujukan transaksional
 * dan kemampuan down() mengembalikan bentuk lama.
 */
class ProductDetailMigrationTest extends TestCase
{
    use RefreshDatabase;

    private object $migration;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migration = require database_path('migrations/2026_11_14_000060_adopt_artesis_product_details.php');
    }

    public function test_migration_maps_parents_variants_and_references(): void
    {
        // Kembali ke bentuk lama, isi data representatif, lalu
        // jalankan migrasi ke bentuk baru.
        $this->migration->down();
        $this->assertTrue(Schema::hasTable('product_parents'));
        $this->assertTrue(Schema::hasColumn('products', 'sku'));

        $tenant = Tenant::create(['name' => 'Optik Uji', 'slug' => 'optik-uji']);
        $categories = $this->seedCategories();
        $branch = Branch::factory()->create();

        $parentFrame = $this->insertParent($tenant->id, $categories['frame'], 'frame', 'Frame Titanium', 'Acme');
        $parentLens = $this->insertParent($tenant->id, $categories['lens'], 'lens', 'Lensa Progresif X', 'Essilor');

        $frame = $this->insertVariant($tenant->id, $parentFrame, [
            'sku' => 'FRM-001', 'type' => 'frame', 'name' => 'Frame Titanium', 'brand' => 'Acme', 'color' => 'Hitam',
        ]);
        $lens = $this->insertVariant($tenant->id, $parentLens, [
            'sku' => 'LNS-001', 'type' => 'lens', 'name' => 'Lensa Progresif X', 'brand' => 'Essilor',
            'sph' => -2.00, 'cyl' => -0.50, 'eye_side' => 'R',
        ]);
        $this->insertAttributes($lens, ['index' => '1.56', 'lens_kind' => 'progressive photochromic', 'coating' => 'bluecut', 'diameter' => '70']);

        $soft = $this->insertVariant($tenant->id, null, [
            'sku' => 'SFT-001', 'type' => 'softlens', 'name' => 'Softlens Bulanan', 'brand' => 'Acuvue',
            'color' => 'Gray', 'power' => -3.25,
        ]);
        $this->insertAttributes($soft, ['wear_period' => 'monthly', 'expiry_tracked' => '1']);

        $rxParent = $this->insertParent($tenant->id, $categories['rx'], 'rx', 'Lensa RX Custom', null);
        $rx = $this->insertVariant($tenant->id, $rxParent, [
            'sku' => 'RX-001', 'type' => 'rx', 'name' => 'Lensa RX Custom', 'brand' => null,
        ]);
        $this->insertAttributes($rx, ['sph_min' => '-10', 'sph_max' => '0', 'cyl_min' => '-4', 'cyl_max' => '0']);

        $sale = Sale::factory()->create(['branch_id' => $branch->id]);
        DB::table('sale_items')->insert([
            'sale_id' => $sale->id, 'product_id' => $frame, 'product_name_snapshot' => 'Frame Titanium',
            'type' => 'frame', 'qty' => 1, 'unit_price' => 500000, 'discount_amount' => 0, 'line_total' => 500000,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('stock_layers')->insert([
            'product_id' => $lens, 'branch_id' => $branch->id, 'qty_received' => 4, 'qty_remaining' => 4,
            'unit_cost' => 250000, 'is_opening' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->migration->up();

        // Bentuk baru: induk hilang, products menjadi master.
        $this->assertFalse(Schema::hasTable('product_parents'));
        $this->assertFalse(Schema::hasColumn('products', 'sku'));
        $this->assertTrue(Schema::hasColumn('products', 'category_id'));

        $masterFrame = DB::table('products')->where('legacy_parent_id', $parentFrame)->first();
        $this->assertNotNull($masterFrame);
        $this->assertSame('Frame Titanium', $masterFrame->name);
        $this->assertSame($categories['frame'], (int) $masterFrame->category_id);
        $this->assertSame($tenant->id, (int) $masterFrame->tenant_id);

        $frameDetail = DB::table('frame_details')->where('id', $frame)->first();
        $this->assertSame('FRM-001', $frameDetail->sku);
        $this->assertSame('Hitam', $frameDetail->color);
        $this->assertSame((int) $masterFrame->id, (int) $frameDetail->product_id);
        $this->assertSame($tenant->id, (int) $frameDetail->tenant_id);

        $lensDetail = DB::table('lens_details')->where('id', $lens)->first();
        $this->assertSame('Progresif', $lensDetail->type);
        $this->assertSame('1.56', $lensDetail->index);
        $this->assertSame('R', $lensDetail->eye_side);
        $this->assertSame('-2.00', (string) $lensDetail->sph);

        // Atribut lensa: index & lens_kind menjadi kolom; sisanya
        // tetap atribut berkunci morph.
        $lensAttrs = DB::table('product_detail_attributes')
            ->where('detail_id', $lens)->where('detail_type', 'lens')
            ->pluck('value', 'key')->all();
        $this->assertSame(['coating' => 'bluecut', 'diameter' => '70'], $lensAttrs);

        // Yatim menjadi master kelompok tanpa legacy_parent_id.
        $softDetail = DB::table('softlens_details')->where('id', $soft)->first();
        $this->assertSame('-3.25', (string) $softDetail->power);
        // Penanda kedaluwarsa naik kelas dari atribut menjadi kolom.
        $this->assertTrue((bool) $softDetail->expiry_tracked);
        $softAttrs = DB::table('product_detail_attributes')
            ->where('detail_id', $soft)->where('detail_type', 'softlens')
            ->pluck('value', 'key')->all();
        $this->assertSame(['wear_period' => 'monthly'], $softAttrs);
        $softMaster = DB::table('products')->where('id', $softDetail->product_id)->first();
        $this->assertSame('Softlens Bulanan', $softMaster->name);
        $this->assertNull($softMaster->legacy_parent_id);
        $this->assertSame($categories['softlens'], (int) $softMaster->category_id);

        $rxAttrs = DB::table('product_detail_attributes')
            ->where('detail_id', $rx)->where('detail_type', 'rx')
            ->pluck('value', 'key')->all();
        $this->assertSame('-10', $rxAttrs['sph_min']);

        // Rujukan transaksional berubah menjadi morph, angka tetap.
        $this->assertFalse(Schema::hasColumn('sale_items', 'product_id'));
        $saleItem = DB::table('sale_items')->first();
        $this->assertSame($frame, (int) $saleItem->detail_id);
        $this->assertSame('frame', $saleItem->detail_type);
        $layer = DB::table('stock_layers')->first();
        $this->assertSame($lens, (int) $layer->detail_id);
        $this->assertSame('lens', $layer->detail_type);

        // down() mengembalikan bentuk lama dengan setia.
        $this->migration->down();
        $this->assertTrue(Schema::hasTable('product_parents'));
        $restored = DB::table('products')->where('id', $frame)->first();
        $this->assertSame('FRM-001', $restored->sku);
        $this->assertSame($parentFrame, (int) $restored->parent_id);
        $restoredItem = DB::table('sale_items')->first();
        $this->assertSame($frame, (int) $restoredItem->product_id);
        $restoredLensAttrs = DB::table('product_attributes')
            ->where('product_id', $lens)->pluck('value', 'key')->all();
        $this->assertSame('1.56', $restoredLensAttrs['index']);
        $this->assertSame('bluecut', $restoredLensAttrs['coating']);
        $restoredSoftAttrs = DB::table('product_attributes')
            ->where('product_id', $soft)->pluck('value', 'key')->all();
        $this->assertSame('1', $restoredSoftAttrs['expiry_tracked']);
        $this->assertSame('monthly', $restoredSoftAttrs['wear_period']);
    }

    /** @return array<string, int> kategori bawaan seeder, per tipe */
    private function seedCategories(): array
    {
        $ids = [];
        foreach (['frame', 'lens', 'rx', 'softlens', 'accessory'] as $type) {
            $ids[$type] = (int) DB::table('product_categories')
                ->where('type', $type)->where('is_active', true)
                ->orderBy('sort_order')->orderBy('id')
                ->value('id');
        }

        return $ids;
    }

    private function insertParent(?int $tenantId, ?int $categoryId, string $type, string $name, ?string $brand): int
    {
        return (int) DB::table('product_parents')->insertGetId([
            'tenant_id' => $tenantId, 'category_id' => $categoryId, 'type' => $type,
            'name' => $name, 'brand' => $brand, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $data */
    private function insertVariant(?int $tenantId, ?int $parentId, array $data): int
    {
        return (int) DB::table('products')->insertGetId([
            'tenant_id' => $tenantId, 'parent_id' => $parentId,
            'sku' => $data['sku'], 'type' => $data['type'], 'name' => $data['name'], 'brand' => $data['brand'] ?? null,
            'color' => $data['color'] ?? null,
            'sph' => $data['sph'] ?? null, 'cyl' => $data['cyl'] ?? null, 'add' => $data['add'] ?? null,
            'power' => $data['power'] ?? null, 'eye_side' => $data['eye_side'] ?? null,
            'cost_price' => 100000, 'sale_price' => 200000, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ] + array_filter([
            'is_process_item' => $data['is_process_item'] ?? null,
        ], fn ($v) => $v !== null));
    }

    /** @param array<string, string> $attrs */
    private function insertAttributes(int $productId, array $attrs): void
    {
        foreach ($attrs as $key => $value) {
            DB::table('product_attributes')->insert([
                'product_id' => $productId, 'key' => $key, 'value' => $value,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }
}
