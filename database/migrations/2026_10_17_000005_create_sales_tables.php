<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penjualan (Blueprint B.3) + penghitung nomor dokumen (E.4).
 * sale_installments lahir di Fase 3; tabelnya belum dibuat di sini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_counters', function (Blueprint $table) {
            $table->id();
            $table->string('prefix', 10);
            $table->string('period', 6); // YYYYMM
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['prefix', 'period']);
        });

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('number', 25)->unique();
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('prescription_id')->nullable()->constrained()->nullOnDelete();
            $table->json('prescription_snapshot')->nullable();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('cashier_id')->constrained('users');
            $table->foreignId('doctor_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('insurance_id')->nullable()->constrained()->nullOnDelete();
            $table->date('sale_date');
            $table->decimal('subtotal_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->enum('tax_mode', ['inclusive', 'exclusive'])->default('inclusive');
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->enum('status', ['active', 'void'])->default('active');
            $table->string('void_reason', 255)->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'sale_date']);
            $table->index('customer_id');
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name_snapshot', 150);
            $table->string('type', 20);
            $table->unsignedInteger('qty');
            $table->decimal('unit_price', 15, 2);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2);
            $table->enum('prescription_ref', ['od', 'os', 'pair'])->nullable();
            $table->timestamps();
        });

        Schema::create('sale_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->enum('method', ['cash', 'debit', 'qris', 'transfer', 'card', 'points']);
            $table->decimal('amount', 15, 2);
            $table->timestamp('paid_at');
            $table->string('reference', 80)->nullable();
            $table->foreignId('received_by')->constrained('users');
            $table->foreignId('reversal_of_id')->nullable()->constrained('sale_payments')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('sale_returns', function (Blueprint $table) {
            $table->id();
            $table->string('number', 25)->unique();
            $table->foreignId('sale_id')->constrained();
            $table->foreignId('branch_id')->constrained();
            $table->string('reason', 255);
            $table->enum('refund_method', ['cash', 'transfer', 'store_credit']);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('sale_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_item_id')->constrained();
            $table->unsignedInteger('qty');
            $table->enum('condition', ['resale', 'damaged']);
            $table->decimal('amount', 15, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_return_items');
        Schema::dropIfExists('sale_returns');
        Schema::dropIfExists('sale_payments');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('document_counters');
    }
};
