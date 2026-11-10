<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Putaran koreksi audit independen (temuan 6) — kebijakan
 * penghapusan tenant untuk FK tenant yang dibuat migrasi yang
 * SUDAH merge (000048 cabang & pengguna, 000049 produk, 000052
 * peran & pengaturan, 000053 master): seluruhnya memakai
 * nullOnDelete, sehingga menghapus baris tenant akan mengubah
 * data bisnis miliknya menjadi tenant_id NULL — dan NULL adalah
 * ember baca instalasi mandiri. Kepemilikan data bisnis tidak
 * boleh berubah karena penghapusan tenant: FK diganti menjadi
 * restrictOnDelete, basis data menolak penghapusan tenant selama
 * data bisnisnya masih ada. Tenant dinonaktifkan lewat
 * is_active (lifecycle S-01), bukan dihapus.
 *
 * Yang SENGAJA tidak diubah: tenant_subscriptions &
 * tenant_feature_overrides & tenant_billing_requests memakai
 * cascadeOnDelete — ketiganya catatan administratif TENTANG
 * tenant itu sendiri (bukan data bisnis yang dapat terbaca
 * instalasi mandiri), dan terhapus bersama tenant adalah
 * perilaku yang benar untuknya.
 */
return new class extends Migration
{
    /** @var string[] tabel data bisnis berkolom tenant_id dari migrasi terdahulu */
    private array $tables = [
        'branches',
        'users',
        'products',
        'roles',
        'settings',
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
