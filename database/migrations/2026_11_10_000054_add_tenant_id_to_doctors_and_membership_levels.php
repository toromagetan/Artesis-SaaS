<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul C — Master lanjutan per tenant (audit ulang isolasi
 * 10 Nov 2026): dokter dan level membership adalah master yang
 * dirawat tiap bisnis (sekelas supplier/asuransi pada S-02),
 * bukan referensi global. tenant_id nullable: baris lama NULL
 * = data instalasi mandiri / warisan, hanya terlihat platform
 * & konteks mandiri; baris bertenant hanya terlihat tenantnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['doctors', 'membership_levels'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreignId('tenant_id')->nullable()->after('id')
                    ->constrained('tenants')->nullOnDelete();
                $blueprint->index('tenant_id');
            });
        }
    }

    public function down(): void
    {
        foreach (['doctors', 'membership_levels'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropConstrainedForeignId('tenant_id');
            });
        }
    }
};
