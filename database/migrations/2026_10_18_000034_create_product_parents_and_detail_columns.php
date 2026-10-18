<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ADR-015 — katalog dua tingkat menyamai model produk Artesis.
 *
 * - `product_parents` menjadi induk; baris `products` menjadi varian
 *   (tetap satu-satunya unit penyimpan stok — rujukan transaksional
 *   tidak berubah).
 * - Kolom detail per tipe pada varian: lensa sph/cyl/add/eye_side,
 *   softlens power (naik kelas dari atribut), gambar per varian.
 * - Produk lama dikelompokkan menjadi induk menurut (type, brand, name).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_parents', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('brand', 80)->nullable();
            $table->string('type', 20);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['type', 'name']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('id')->constrained('product_parents')->nullOnDelete();
            $table->decimal('sph', 6, 2)->nullable();
            $table->decimal('cyl', 6, 2)->nullable();
            $table->decimal('add', 6, 2)->nullable();
            $table->decimal('power', 6, 2)->nullable();
            $table->string('eye_side', 10)->nullable();
            $table->string('image_path')->nullable();
        });

        // Kelompokkan produk lama menjadi induk (type, brand, name).
        $groups = DB::table('products')
            ->select('type', 'brand', 'name')
            ->groupBy('type', 'brand', 'name')
            ->get();
        foreach ($groups as $group) {
            $parentId = DB::table('product_parents')->insertGetId([
                'name' => $group->name,
                'brand' => $group->brand,
                'type' => $group->type,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('products')
                ->where('type', $group->type)
                ->where('name', $group->name)
                ->where(fn ($q) => $group->brand === null ? $q->whereNull('brand') : $q->where('brand', $group->brand))
                ->update(['parent_id' => $parentId]);
        }

        // Power softlens: atribut lama naik kelas menjadi kolom.
        $powers = DB::table('product_attributes')->where('key', 'power')->get();
        foreach ($powers as $row) {
            if (is_numeric($row->value)) {
                DB::table('products')->where('id', $row->product_id)->update(['power' => $row->value]);
            }
        }
        DB::table('product_attributes')->where('key', 'power')->delete();
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn(['sph', 'cyl', 'add', 'power', 'eye_side', 'image_path']);
        });
        Schema::dropIfExists('product_parents');
    }
};
