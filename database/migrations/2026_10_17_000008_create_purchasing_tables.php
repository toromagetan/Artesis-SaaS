<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pembelian (Blueprint B.4/Purchasing): PR → PO → Receipt → pembayaran
 * beralokasi ke receipt → retur pembelian. Sisa tagihan receipt selalu
 * TERHITUNG (total − alokasi − retur pengurang), tidak disimpan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('requester_id')->constrained('users');
            $table->foreignId('branch_id')->constrained();
            $table->enum('status', ['draft', 'submitted', 'approved', 'converted', 'cancelled'])->default('draft');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'created_at']);
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('supplier_id')->constrained();
            $table->foreignId('branch_id')->constrained();
            $table->date('order_date');
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->enum('status', ['draft', 'sent', 'partial', 'complete', 'void'])->default('draft');
            $table->string('void_reason')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['branch_id', 'created_at']);
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->integer('qty_ordered');
            $table->integer('qty_received')->default(0);
            $table->decimal('unit_cost', 15, 2);
            $table->decimal('line_total', 15, 2);
            $table->timestamps();
        });

        Schema::create('purchase_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->integer('qty');
            $table->decimal('estimated_price', 15, 2)->nullable();
            $table->foreignId('supplier_id')->nullable()->constrained();
            $table->foreignId('purchase_order_id')->nullable()->constrained();
            $table->timestamps();
        });

        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('purchase_order_id')->constrained();
            $table->date('received_date');
            $table->string('delivery_note_no', 80)->nullable();
            $table->foreignId('received_by')->constrained('users');
            $table->timestamps();

            $table->index(['purchase_order_id', 'received_date']);
        });

        Schema::create('receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->constrained();
            $table->integer('qty');
            $table->decimal('unit_cost_actual', 15, 2);
            $table->timestamps();

            $table->unique(['receipt_id', 'purchase_order_item_id']);
        });

        Schema::create('purchase_payments', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('supplier_id')->constrained();
            $table->enum('method', ['transfer', 'cash', 'giro', 'other']);
            $table->decimal('amount', 15, 2);
            $table->date('payment_date');
            $table->string('reference', 80)->nullable();
            $table->foreignId('paid_by')->constrained('users');
            $table->foreignId('reversal_of_id')->nullable()
                ->constrained('purchase_payments')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('purchase_payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('receipt_id')->constrained();
            $table->decimal('amount', 15, 2);
            $table->timestamps();

            $table->index(['receipt_id']);
        });

        Schema::create('purchase_returns', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('receipt_id')->constrained();
            $table->string('reason');
            $table->decimal('total_amount', 15, 2);
            $table->enum('credit_status', ['reduces_payable', 'supplier_credit']);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('purchase_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('receipt_item_id')->constrained();
            $table->integer('qty');
            $table->decimal('amount', 15, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_return_items');
        Schema::dropIfExists('purchase_returns');
        Schema::dropIfExists('purchase_payment_allocations');
        Schema::dropIfExists('purchase_payments');
        Schema::dropIfExists('receipt_items');
        Schema::dropIfExists('receipts');
        Schema::dropIfExists('purchase_request_items');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('purchase_requests');
    }
};
