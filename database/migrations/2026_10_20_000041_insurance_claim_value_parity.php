<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penyelarasan M3 dengan Artesis (keputusan owner 2026-10-20):
 * - Master asuransi Artesis memiliki claim_value (nilai klaim yang
 *   ditanggung per asuransi; POS mengisikannya otomatis sebagai
 *   bawaan klaim) — ditambahkan ke insurances. Kolom phone tidak
 *   ada di Artesis — dicabut.
 * - Artesis TIDAK mengelola penerimaan klaim dari perusahaan
 *   asuransi: insurance_usages murni histori snapshot (laporan).
 *   Tabel insurance_claim_receipts (tambahan M3 versi pertama)
 *   dicabut.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('insurances', function (Blueprint $table) {
            $table->decimal('claim_value', 15, 2)->default(0)->after('name');
            $table->dropColumn('phone');
        });

        Schema::dropIfExists('insurance_claim_receipts');
    }

    public function down(): void
    {
        Schema::create('insurance_claim_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('insurance_usage_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('method', 20);
            $table->dateTime('received_at');
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::table('insurances', function (Blueprint $table) {
            $table->string('phone', 30)->nullable()->after('name');
            $table->dropColumn('claim_value');
        });
    }
};
