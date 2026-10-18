<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kategori produk sebagai entitas terpisah (paritas Artesis,
 * lanjutan ADR-015): kategori dikelola sebagai data (nama +
 * deskripsi), terpetakan 1:1 ke tipe sistem yang menjadi kunci
 * perilaku. Induk produk memegang category_id; type induk tetap
 * diselaraskan dari kategorinya.
 */
return new class extends Migration
{
    /** Peta tipe sistem → [nama, deskripsi] lima kategori bawaan. */
    private const DEFAULTS = [
        'frame' => ['Frame', 'Kacamata frame untuk berbagai jenis lensa'],
        'lens' => ['Lensa', 'Lensa siap pakai dengan ukuran standar'],
        'rx' => ['Lensa RX', 'Lensa resep sesuai kebutuhan mata pelanggan'],
        'softlens' => ['Softlens', 'Lensa kontak lunak dengan berbagai warna dan ukuran'],
        'accessory' => ['Aksesoris', 'Aksesoris pendukung seperti pembersih lensa, case, dan lainnya'],
    ];

    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->string('description')->nullable();
            $table->string('type', 20);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $ids = [];
        $order = 0;
        foreach (self::DEFAULTS as $type => [$name, $description]) {
            $ids[$type] = DB::table('product_categories')->insertGetId([
                'name' => $name,
                'description' => $description,
                'type' => $type,
                'is_active' => true,
                'sort_order' => $order++,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('product_parents', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('brand')->constrained('product_categories')->restrictOnDelete();
        });

        foreach ($ids as $type => $categoryId) {
            DB::table('product_parents')->where('type', $type)->update(['category_id' => $categoryId]);
        }
    }

    public function down(): void
    {
        Schema::table('product_parents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });
        Schema::dropIfExists('product_categories');
    }
};
