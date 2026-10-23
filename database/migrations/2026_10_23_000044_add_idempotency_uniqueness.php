<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengerasan idempotensi basis data (audit 23 Okt 2026 C5).
 *
 * Jurnal: kunci unik (source_type, source_id) secara menyeluruh
 * TIDAK aman — penyusutan sah membuat banyak jurnal untuk
 * (depreciation, aset) yang sama lintas periode, dan jurnal
 * manual/payroll memakai source_id NULL. Karena itu keunikan
 * hanya ditegakkan untuk jalur postOnce lewat kolom khusus
 * idempotency_key (NULL untuk semua jalur lain; MySQL
 * memperbolehkan banyak NULL pada indeks unik).
 *
 * Poin: keunikan hanya untuk baris earn per penjualan — pola
 * yang sudah dijaga PointsService::earnForSale di kode — lewat
 * kolom ter-generate yang NULL untuk tipe/referensi lain
 * (redeem berkali-kali per penjualan tetap sah).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journals', function (Blueprint $table) {
            $table->string('idempotency_key', 120)->nullable()->unique()->after('source_id');
        });

        Schema::table('points_ledger', function (Blueprint $table) {
            $table->unsignedBigInteger('earn_sale_key')->nullable()
                ->storedAs("case when type = 'earn' and reference_type = 'sale' then reference_id else null end")
                ->unique();
        });
    }

    public function down(): void
    {
        Schema::table('points_ledger', function (Blueprint $table) {
            $table->dropUnique(['earn_sale_key']);
            $table->dropColumn('earn_sale_key');
        });
        Schema::table('journals', function (Blueprint $table) {
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn('idempotency_key');
        });
    }
};
