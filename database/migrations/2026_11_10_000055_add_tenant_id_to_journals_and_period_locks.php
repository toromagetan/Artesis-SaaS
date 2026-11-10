<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul B1 — Akuntansi per tenant (audit ulang isolasi
 * 10 Nov 2026): buku besar berhenti menjadi satu buku
 * se-instalasi. Jurnal mendapat tenant_id (dicap PostingService
 * dari aktor/sumbernya); kunci periode menjadi per (periode,
 * tenant) — satu tenant menutup bulan tidak lagi mengunci
 * tenant lain. Baris lama tenant_id NULL = buku instalasi
 * mandiri/warisan, hanya terlihat konteks mandiri & platform.
 *
 * Putaran koreksi audit independen (temuan 1 + 6):
 * - Keunikan kunci periode ditegakkan lewat kolom generated
 *   tenant_key = COALESCE(tenant_id, 0) dengan indeks unik
 *   (period, tenant_key). Indeks unik gabungan biasa TIDAK
 *   menjamin keunikan baris NULL di MySQL (NULL != NULL),
 *   sehingga ember instalasi mandiri sebelumnya dapat memiliki
 *   banyak kunci untuk periode yang sama.
 * - FK tenant memakai restrictOnDelete: menghapus tenant yang
 *   masih memiliki jurnal/kunci TIDAK lagi mengubah kepemilikan
 *   data bisnis menjadi NULL (ember instalasi mandiri).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journals', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')
                ->constrained('tenants')->restrictOnDelete();
            $table->index('tenant_id');
        });

        Schema::table('period_locks', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('period')
                ->constrained('tenants')->restrictOnDelete();
            $table->unsignedBigInteger('tenant_key')
                ->storedAs('COALESCE(tenant_id, 0)');
        });
        Schema::table('period_locks', function (Blueprint $table) {
            $table->dropUnique(['period']);
            $table->unique(['period', 'tenant_key']);
        });
    }

    public function down(): void
    {
        Schema::table('period_locks', function (Blueprint $table) {
            $table->dropUnique(['period', 'tenant_key']);
            $table->unique('period');
        });
        Schema::table('period_locks', function (Blueprint $table) {
            $table->dropColumn('tenant_key');
            $table->dropConstrainedForeignId('tenant_id');
        });
        Schema::table('journals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
        });
    }
};
