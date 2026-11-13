<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul L-01 — Audit log per tenant (audit menyeluruh A-04).
 *
 * audit_logs selama ini hanya menyimpan branch_id; catatan tanpa
 * cabang (mis. pembuatan produk) tidak terlihat oleh aktor tenant
 * mana pun karena pembaca memagar daftar murni berdasarkan cabang
 * terkelola. Kolom tenant_id dicap AuditLogger dari konteks
 * tenant tervalidasi milik aktor penulis — bukan dari input —
 * dan menjadi dasar pagar baca per tenant. Baris lama tenant_id
 * NULL = warisan pra-L-01/instalasi mandiri; pembaca tenant tetap
 * menjangkaunya hanya lewat cabang terkelolanya (lihat
 * AuditLogController). FK restrictOnDelete sejajar pola B1:
 * menghapus tenant tidak boleh mengubah kepemilikan jejak audit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')
                ->constrained('tenants')->restrictOnDelete();
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
        });
    }
};
