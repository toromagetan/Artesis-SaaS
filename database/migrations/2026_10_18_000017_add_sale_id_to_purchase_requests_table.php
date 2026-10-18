<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Paritas Artesis (koreksi owner 2026-10-18): PR dapat lahir OTOMATIS
     * dari sebuah penjualan untuk kekurangan lensa — tertaut ke penjualan
     * asalnya lewat sale_id (Artesis menautkan via invoice_number).
     */
    public function up(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->foreignId('sale_id')->nullable()->after('branch_id')
                ->constrained('sales')->nullOnDelete();
            $table->index(['sale_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sale_id');
        });
    }
};
