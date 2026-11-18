<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ISO-1 (audit isolasi menyeluruh 18 Nov 2026): nomor dokumen per
 * tenant. Sebelumnya document_counters adalah satu tangga global
 * per (prefix, bulan) — celah nomor pada dokumen satu tenant
 * membocorkan volume aktivitas tenant lain, dan nomor tenant tidak
 * berurutan di dalam tenantnya sendiri.
 *
 * - document_counters menerima tenant_id (NULL = ember platform /
 *   instalasi mandiri) + tenant_key VIRTUAL (pola 000051/000065);
 *   keunikan penghitung menjadi (prefix, period, tenant_key).
 *   Baris lama otomatis menjadi ember NULL.
 * - Keunikan GLOBAL pada kolom nomor dokumen dicabut (diganti
 *   indeks biasa untuk pencarian): dua tenant kini sah memiliki
 *   nomor yang sama (mis. INV-202611-0001) karena urutan dijamin
 *   penghitung per ember yang dikunci barisnya di dalam transaksi
 *   dokumen. Tabel: sales, journals, purchase_orders,
 *   purchase_requests, purchase_payments, purchase_returns,
 *   receipts, sale_returns, stock_transfers, warranty_cases, dan
 *   customer_credits (credit_number).
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $numberTables = [
        'sales', 'journals', 'purchase_orders', 'purchase_requests',
        'purchase_payments', 'purchase_returns', 'receipts',
        'sale_returns', 'stock_transfers', 'warranty_cases',
    ];

    public function up(): void
    {
        Schema::table('document_counters', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')
                ->constrained('tenants')->restrictOnDelete();
            $table->index('tenant_id');
            $table->unsignedBigInteger('tenant_key')
                ->virtualAs('COALESCE(tenant_id, 0)');
        });
        Schema::table('document_counters', function (Blueprint $table) {
            $table->dropUnique(['prefix', 'period']);
            $table->unique(['prefix', 'period', 'tenant_key']);
        });

        foreach ($this->numberTables as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropUnique(['number']);
                $table->index('number');
            });
        }
        Schema::table('customer_credits', function (Blueprint $table) {
            $table->dropUnique(['credit_number']);
            $table->index('credit_number');
        });
    }

    public function down(): void
    {
        Schema::table('customer_credits', function (Blueprint $table) {
            $table->dropIndex(['credit_number']);
            $table->unique('credit_number');
        });
        foreach (array_reverse($this->numberTables) as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropIndex(['number']);
                $table->unique('number');
            });
        }

        Schema::table('document_counters', function (Blueprint $table) {
            $table->dropUnique(['prefix', 'period', 'tenant_key']);
            $table->unique(['prefix', 'period']);
        });
        Schema::table('document_counters', function (Blueprint $table) {
            $table->dropColumn('tenant_key');
            $table->dropConstrainedForeignId('tenant_id');
        });
    }
};
