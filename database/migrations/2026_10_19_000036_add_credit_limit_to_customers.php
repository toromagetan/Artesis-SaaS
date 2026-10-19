<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Limit kredit formal pelanggan (paritas Artesis): NULL = tanpa
 * limit formal (perilaku lama). Penjualan baru yang membuat total
 * piutang beredar pelanggan melampaui limit ditolak SalesService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->decimal('credit_limit', 15, 2)->nullable()->after('insurance_id');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('credit_limit');
        });
    }
};
