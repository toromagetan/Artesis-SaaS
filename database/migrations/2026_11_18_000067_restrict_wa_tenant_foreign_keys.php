<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengerasan WA (verifikasi audit independen 18 Nov 2026, temuan A)
 * — menyelaraskan FK tenant tabel WhatsApp ke kebijakan migrasi
 * 000057. Tabel bisnis lain sudah restrictOnDelete sejak 000057
 * (atau lahir restrict), tetapi wa_gateways & wa_messages (000061)
 * dan wa_campaigns (000064) memperoleh tenant_id SESUDAH kebijakan
 * itu dan memakai nullOnDelete: menghapus baris tenant akan
 * mengubah gateway, pesan, dan campaign miliknya menjadi
 * tenant_id NULL — padahal NULL adalah ember global/platform/
 * instalasi mandiri, sehingga data tenant berubah klasifikasi
 * menjadi data global. Kepemilikan data bisnis tidak boleh berubah
 * karena penghapusan tenant: basis data menolak penghapusan tenant
 * selama data WA-nya masih ada. Tenant dinonaktifkan lewat
 * is_active (lifecycle S-01), bukan dihapus.
 */
return new class extends Migration
{
    /** @var string[] tabel WhatsApp berkolom tenant_id dari migrasi 000061/000064 */
    private array $tables = [
        'wa_gateways',
        'wa_messages',
        'wa_campaigns',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $schema) {
                $schema->dropForeign(['tenant_id']);
            });
            Schema::table($table, function (Blueprint $schema) {
                $schema->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $schema) {
                $schema->dropForeign(['tenant_id']);
            });
            Schema::table($table, function (Blueprint $schema) {
                $schema->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
            });
        }
    }
};
