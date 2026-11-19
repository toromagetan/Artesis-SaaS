<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Keputusan C — PR-1 (keputusan owner 2026-11-18, skema penuh
 * per tenant): sepuluh tabel dokumen bisnis menerima kolom
 * tenant_id agar keunikan nomor dokumen per tenant kelak dapat
 * ditegakkan basis data (PR-2 menambah tenant_key + unique
 * SESUDAH audit antara lolos). journals sudah memiliki
 * tenant_id sejak 000055 dan tidak disentuh di sini.
 *
 * Tahap ini TANPA unique dan TANPA kolom tergenerasi:
 * - tenant_id nullable — NULL adalah ember instalasi mandiri /
 *   platform, persis semantik ember penghitung Numbering.
 * - FK restrictOnDelete mengikuti kebijakan 000057 untuk tabel
 *   bisnis bertenant (tenant berdokumen tidak dapat dihapus).
 * - Indeks biasa untuk isi ulang, pemeriksaan, dan kueri audit.
 *
 * Pengisian baris lama dijalankan perintah artisan terpisah
 * `lentera:backfill-tenant-dokumen` (idempoten, dapat diaudit
 * dan diulang) — SENGAJA tidak di dalam migrasi. Dokumen baru
 * dicap saat pembuatan oleh service masing-masing dari ember
 * yang sama dengan yang dipakai Numbering mencetak nomornya.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $tables = [
        'sales',
        'sale_returns',
        'purchase_requests',
        'purchase_orders',
        'receipts',
        'purchase_payments',
        'purchase_returns',
        'stock_transfers',
        'warranty_cases',
        'customer_credits',
    ];

    public function up(): void
    {
        foreach ($this->tables as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('tenant_id')->nullable()->after('id')
                    ->constrained('tenants')->restrictOnDelete();
                $table->index('tenant_id');
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->tables) as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropForeign(['tenant_id']);
                $table->dropColumn('tenant_id');
            });
        }
    }
};
