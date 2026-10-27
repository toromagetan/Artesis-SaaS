<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add-on proses (koreksi owner 2026-10-27, paritas Artesis): aksesoris
 * yang merupakan pekerjaan menyertai lensa RX (coating dsb.) ditandai
 * is_process_item — bukan barang ready stok. Add-on proses dibebaskan
 * dari pagar stok ketat aksesoris dan masuk PR otomatis penjualan
 * seperti Lensa RX. Aksesoris tanpa tanda ini tidak berubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_process_item')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('is_process_item');
        });
    }
};
