<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * B-01 — Penegakan database untuk invariant "maksimal satu
 * permintaan billing berstatus pending per tenant".
 *
 * MySQL tidak mempunyai unique index parsial; polanya adalah
 * kolom tergenerasi yang bernilai tenant_id HANYA saat status
 * pending (selain itu NULL, dan NULL tidak ikut unique) + unique
 * index atas kolom itu. Lapisan aplikasi (kunci baris tenant di
 * BillingService) memberi pesan penolakan yang ramah; constraint
 * ini adalah lapisan terakhir yang tetap berlaku bahkan bila ada
 * jalur tulis lain di masa depan.
 *
 * Catatan: migrasi ini GAGAL dengan sengaja bila data lama sudah
 * memuat pending ganda untuk satu tenant — keadaan itu harus
 * dibersihkan manual lebih dulu, bukan disembunyikan.
 */
return new class extends Migration
{
    public function up(): void
    {
        // VIRTUAL (bukan STORED): kolom STORED yang berekspresi di
        // atas kolom foreign key (tenant_id, cascadeOnDelete)
        // ditolak InnoDB (error 1215). Kolom VIRTUAL boleh dirujuk
        // FK dan tetap dapat di-index — nilai uniknya tersimpan di
        // dalam index itu sendiri.
        DB::statement(
            'ALTER TABLE tenant_billing_requests ADD COLUMN pending_tenant_id BIGINT UNSIGNED '
            ."GENERATED ALWAYS AS (CASE WHEN status = 'pending' THEN tenant_id ELSE NULL END) VIRTUAL",
        );

        Schema::table('tenant_billing_requests', function (Blueprint $table) {
            $table->unique('pending_tenant_id', 'tenant_billing_requests_pending_unique');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_billing_requests', function (Blueprint $table) {
            $table->dropUnique('tenant_billing_requests_pending_unique');
        });

        DB::statement('ALTER TABLE tenant_billing_requests DROP COLUMN pending_tenant_id');
    }
};
