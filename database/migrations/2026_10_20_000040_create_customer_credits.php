<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Penyelarasan M2 dengan Artesis (keputusan owner 2026-10-20):
 * Artesis TIDAK mengenal plafon piutang (credit_limit) — fitur
 * sebenarnya adalah VOUCHER KREDIT PELANGGAN (CustomerCredit) yang
 * terbit dari refund bermetode kredit toko dan diterapkan ke
 * penjualan lain lewat jalur terkontrol. Migrasi ini:
 * - membuat customer_credits + customer_credit_applications,
 * - menambah metode 'store_credit' ke ENUM sale_payments.method
 *   (hanya diisi CustomerCreditService, bukan pilihan manual kasir),
 * - mencabut kolom customers.credit_limit (M2 versi pertama).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_credits', function (Blueprint $table) {
            $table->id();
            $table->string('credit_number', 20)->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            $table->foreignId('sale_return_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->decimal('remaining_amount', 15, 2);
            $table->string('status', 20)->default('active');
            $table->string('reason')->nullable();
            $table->dateTime('issued_at');
            $table->dateTime('expires_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('customer_credit_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_credit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->dateTime('applied_at');
            $table->foreignId('applied_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['customer_credit_id', 'sale_id']);
        });

        DB::statement("ALTER TABLE sale_payments MODIFY method ENUM('cash','debit','qris','transfer','card','points','insurance','store_credit') NOT NULL");

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('credit_limit');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->decimal('credit_limit', 15, 2)->nullable();
        });

        DB::statement("ALTER TABLE sale_payments MODIFY method ENUM('cash','debit','qris','transfer','card','points','insurance') NOT NULL");

        Schema::dropIfExists('customer_credit_applications');
        Schema::dropIfExists('customer_credits');
    }
};
