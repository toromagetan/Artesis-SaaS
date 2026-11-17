<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit W-01 (W1-1): kampanye WA menjadi milik tenant pembuatnya.
 * Sebelumnya wa_campaigns tidak berdimensi tenant sama sekali,
 * sehingga resolusi penerima hanya bergantung pada TenantScope
 * ambien — job pengiriman yang berjalan dalam konteks sistem
 * menjangkau pelanggan seluruh instalasi. Kolom NULLABLE: kampanye
 * platform & instalasi mandiri memakai semantik global (NULL).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wa_campaigns', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->nullOnDelete();
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('wa_campaigns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
        });
    }
};
