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
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journals', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')
                ->constrained('tenants')->nullOnDelete();
            $table->index('tenant_id');
        });

        Schema::table('period_locks', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('period')
                ->constrained('tenants')->nullOnDelete();
        });
        Schema::table('period_locks', function (Blueprint $table) {
            $table->dropUnique(['period']);
            $table->unique(['period', 'tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::table('period_locks', function (Blueprint $table) {
            $table->dropUnique(['period', 'tenant_id']);
            $table->unique('period');
        });
        Schema::table('period_locks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
        });
        Schema::table('journals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
        });
    }
};
