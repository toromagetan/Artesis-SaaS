<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S-03 — Kepemilikan per tenant untuk peran & pengaturan.
 *
 * roles.tenant_id NULL = templat platform/instalasi (dibagikan,
 * hanya dapat diubah aktor platform atau pemilik instalasi
 * mandiri); baris bertenant = peran milik tenant itu. Keunikan
 * nama berubah dari global menjadi per-lingkup (nama, tenant_id)
 * agar tenant dapat memiliki peran bernama sama dengan templat
 * atau dengan tenant lain tanpa bertabrakan.
 *
 * settings.tenant_id NULL = nilai global/instalasi; baris
 * bertenant = penimpaan milik tenant itu (resolusi baca:
 * tenant dulu, lalu global — lihat model Setting).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('label')
                ->constrained('tenants')->nullOnDelete();
            $table->dropUnique('roles_name_unique');
            $table->unique(['name', 'tenant_id']);
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('value')
                ->constrained('tenants')->nullOnDelete();
            $table->dropUnique('settings_key_unique');
            $table->unique(['key', 'tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique(['name', 'tenant_id']);
            $table->unique('name');
            $table->dropConstrainedForeignId('tenant_id');
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->dropUnique(['key', 'tenant_id']);
            $table->unique('key');
            $table->dropConstrainedForeignId('tenant_id');
        });
    }
};
