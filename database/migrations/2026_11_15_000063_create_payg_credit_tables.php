<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PAYG murni (keputusan owner 2026-11-15): paket bayar per
 * transaksi penjualan (bawaan Rp500/transaksi) sebagai model
 * TAMBAHAN — tangga langganan tidak berubah. Saldo kredit
 * transaksi prabayar per tenant, meniru pola kredit AI (000062):
 * tenant mengajukan isi ulang (payg_credit_requests; harga
 * dibekukan saat pengajuan), platform mengonfirmasi, saldo
 * bergerak HANYA lewat payg_credit_ledger (append-only;
 * balance_after tercatat di setiap baris). Saldo BOLEH negatif
 * (tunggakan tercatat) — kasir tidak pernah di-hard-stop.
 * balance_after bertanda (signed) karena itu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payg_credit_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedInteger('credits');
            $table->unsignedBigInteger('unit_price');
            $table->unsignedBigInteger('amount');
            $table->enum('status', ['pending', 'confirmed', 'rejected'])->default('pending');
            $table->string('note', 255)->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
        });

        // Penjaga lapis kedua (meniru B-01 penagihan): paling banyak
        // SATU permintaan menunggu per tenant, ditegakkan database.
        // VIRTUAL, bukan STORED: kolom STORED berekspresi di atas
        // kolom foreign key ditolak InnoDB (error 1215).
        DB::statement(
            'ALTER TABLE payg_credit_requests ADD COLUMN pending_tenant_id BIGINT UNSIGNED '
            ."GENERATED ALWAYS AS (CASE WHEN status = 'pending' THEN tenant_id ELSE NULL END) VIRTUAL"
        );
        Schema::table('payg_credit_requests', function (Blueprint $table) {
            $table->unique('pending_tenant_id', 'payg_credit_requests_pending_unique');
        });

        Schema::create('payg_credit_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->enum('kind', ['topup', 'usage', 'reversal']);
            $table->integer('credits_delta');
            $table->integer('balance_after');
            $table->unsignedBigInteger('unit_price')->nullable();
            $table->unsignedBigInteger('amount')->nullable();
            $table->foreignId('request_id')->nullable()->constrained('payg_credit_requests')->nullOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained('sales')->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['tenant_id', 'id']);
            // Satu penjualan = paling banyak satu potongan dan satu
            // pembalikan (baris topup ber-sale_id NULL tidak terkena:
            // MySQL memperbolehkan banyak NULL pada indeks unik).
            $table->unique(['sale_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payg_credit_ledger');
        Schema::dropIfExists('payg_credit_requests');
    }
};
