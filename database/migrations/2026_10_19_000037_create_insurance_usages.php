<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pemakaian asuransi pada penjualan (paritas Artesis): penjualan
 * mencatat insurance_claim_amount (bagian tagihan yang ditanggung
 * asuransi, mengurangi payable pelanggan); insurance_usages =
 * histori snapshot pemakaian; insurance_claim_receipts =
 * penerimaan pembayaran klaim dari perusahaan asuransi
 * (melunasi Piutang Asuransi 1-1700).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('insurance_claim_amount', 15, 2)->default(0)->after('insurance_id');
        });

        Schema::create('insurance_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('insurance_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->dateTime('used_at');
            $table->decimal('transaction_amount', 15, 2);
            $table->decimal('insurance_claim_amount', 15, 2);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('insurance_claim_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('insurance_usage_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('method', 20);
            $table->dateTime('received_at');
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insurance_claim_receipts');
        Schema::dropIfExists('insurance_usages');
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('insurance_claim_amount');
        });
    }
};
