<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S-02 — Isolasi master per tenant (keputusan OD-2: semua data
 * per tenant, tidak boleh menyeberang). Menambah tenant_id ke
 * tabel master yang belum memilikinya: product_parents,
 * customers, prescriptions, insurances, suppliers (products
 * sudah memilikinya sejak 000049).
 *
 * Kolom nullable: NULL = baris era instalasi mandiri/global —
 * hanya terlihat aktor platform & instalasi standalone. Baris
 * milik tenant selalu tercap tenant_id (trait TenantScoped /
 * service). Di dalam satu tenant, master tetap berlaku global
 * antar-cabang (perilaku ADR-012 dipertahankan intra-tenant).
 */
return new class extends Migration
{
    /** @var string[] */
    private array $tables = [
        'product_parents',
        'customers',
        'prescriptions',
        'insurances',
        'suppliers',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $schema) {
                $schema->foreignId('tenant_id')->nullable()
                    ->constrained('tenants')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $schema) {
                $schema->dropConstrainedForeignId('tenant_id');
            });
        }
    }
};
