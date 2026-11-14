<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WA per tenant (keputusan owner 2026-11-14, menutup OD-4):
 * gateway WA menjadi milik masing-masing tenant dan setiap pesan
 * tercatat tenant pemiliknya. Kolom NULLABLE: baris lama & mode
 * instalasi mandiri memakai semantik global (tenant_id NULL).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wa_gateways', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->nullOnDelete();
            $table->index(['tenant_id', 'is_active']);
        });

        Schema::table('wa_messages', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->nullOnDelete();
            $table->index(['tenant_id', 'direction']);
        });
    }

    public function down(): void
    {
        Schema::table('wa_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
        });

        Schema::table('wa_gateways', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
        });
    }
};
