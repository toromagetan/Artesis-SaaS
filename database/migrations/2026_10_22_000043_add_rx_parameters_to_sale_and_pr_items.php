<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR-019: precal manual per item Lensa RX disimpan sebagai JSON
 * rx_parameters pada item penjualan (snapshot resep + precal 11 kunci
 * + flag precal_complete) dan tersalin ke item purchase request saat
 * PR otomatis kekurangan dibuat dari penjualan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->json('rx_parameters')->nullable()->after('prescription_ref');
        });

        Schema::table('purchase_request_items', function (Blueprint $table) {
            $table->json('rx_parameters')->nullable()->after('qty');
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn('rx_parameters');
        });

        Schema::table('purchase_request_items', function (Blueprint $table) {
            $table->dropColumn('rx_parameters');
        });
    }
};
