<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul B2 — Satelit akuntansi per tenant (audit ulang isolasi
 * 10 Nov 2026): aset tetap, payroll, komisi, dan aturan insentif
 * adalah data bisnis milik tenant (payroll memuat nama & gaji
 * karyawan — tidak boleh terbaca lintas tenant). Pola sama
 * dengan S-02/B1: tenant_id nullable; baris lama NULL hanya
 * terlihat platform & instalasi mandiri.
 *
 * Putaran koreksi audit independen (temuan 6): FK tenant memakai
 * restrictOnDelete — menghapus tenant TIDAK boleh mengubah
 * kepemilikan data bisnisnya menjadi NULL (ember instalasi
 * mandiri); basis data menolak penghapusan selama data ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['fixed_assets', 'payrolls', 'commissions', 'incentive_rules'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreignId('tenant_id')->nullable()->after('id')
                    ->constrained('tenants')->restrictOnDelete();
                $blueprint->index('tenant_id');
            });
        }
    }

    public function down(): void
    {
        foreach (['fixed_assets', 'payrolls', 'commissions', 'incentive_rules'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropConstrainedForeignId('tenant_id');
            });
        }
    }
};
