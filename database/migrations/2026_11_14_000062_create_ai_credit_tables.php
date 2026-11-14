<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * AI bayar per penggunaan (keputusan owner 2026-11-14): saldo
 * pertanyaan prabayar per tenant. Tenant mengajukan isi ulang
 * (ai_credit_requests; harga dibekukan saat pengajuan), platform
 * mengonfirmasi, saldo bergerak HANYA lewat ai_credit_ledger
 * (append-only; balance_after tercatat di setiap baris).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_credit_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedInteger('questions');
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
            'ALTER TABLE ai_credit_requests ADD COLUMN pending_tenant_id BIGINT UNSIGNED '
            ."GENERATED ALWAYS AS (CASE WHEN status = 'pending' THEN tenant_id ELSE NULL END) VIRTUAL"
        );
        Schema::table('ai_credit_requests', function (Blueprint $table) {
            $table->unique('pending_tenant_id', 'ai_credit_requests_pending_unique');
        });

        Schema::create('ai_credit_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->enum('kind', ['topup', 'usage']);
            $table->integer('questions_delta');
            $table->unsignedInteger('balance_after');
            $table->unsignedBigInteger('unit_price')->nullable();
            $table->unsignedBigInteger('amount')->nullable();
            $table->foreignId('request_id')->nullable()->constrained('ai_credit_requests')->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['tenant_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_credit_ledger');
        Schema::dropIfExists('ai_credit_requests');
    }
};
