<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F4.3 Expiry softlens: tanggal kedaluwarsa per item penerimaan.
 * Lapisan FIFO mewarisinya lewat receipt_item_id — dasar peringatan
 * < 90 hari, blokir jual kedaluwarsa, dan konsumsi yang melewatkan
 * lapisan kedaluwarsa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipt_items', function (Blueprint $table) {
            $table->date('expiry_date')->nullable()->after('unit_cost_actual');
        });
    }

    public function down(): void
    {
        Schema::table('receipt_items', function (Blueprint $table) {
            $table->dropColumn('expiry_date');
        });
    }
};
