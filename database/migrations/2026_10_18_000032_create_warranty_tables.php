<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul Garansi (paritas alur after-sales Artesis, audit
 * 2026-10-18): kasus garansi per item penjualan, pengganti
 * (dari stok gudang / PO khusus), stok rusak hasil inspeksi
 * barang lama, dan pembayaran biaya pelanggan. Cabang gudang
 * utama ditandai is_main_warehouse (padanan Artesis).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->boolean('is_main_warehouse')->default(false)->after('is_active');
        });

        Schema::create('warranty_cases', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->foreignId('sale_id')->constrained('sales');
            $table->foreignId('sale_item_id')->constrained('sale_items');
            $table->foreignId('branch_id')->constrained('branches');
            $table->string('status', 30)->default('open');
            $table->string('reason', 500);
            $table->text('description')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('warranty_replacements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warranty_case_id')->constrained('warranty_cases')->cascadeOnDelete();
            $table->foreignId('original_sale_item_id')->constrained('sale_items');
            $table->foreignId('replacement_product_id')->nullable()->constrained('products');
            $table->string('source', 20)->default('pending');
            $table->unsignedInteger('quantity');
            $table->decimal('original_value', 15, 2)->default(0);
            $table->decimal('replacement_value', 15, 2)->default(0);
            $table->decimal('customer_charge', 15, 2)->default(0);
            $table->foreignId('shipped_movement_id')->nullable()->constrained('stock_movements')->nullOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->string('status', 20)->default('pending');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('broken_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches');
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('warranty_case_id')->nullable()->constrained('warranty_cases')->nullOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('status', 25)->default('pending_inspection');
            $table->string('resolution', 25)->nullable();
            $table->string('reason', 500)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('resolved_by')->nullable()->constrained('users');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('warranty_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warranty_case_id')->constrained('warranty_cases')->cascadeOnDelete();
            $table->string('method', 20);
            $table->decimal('amount', 15, 2);
            $table->date('payment_date');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warranty_payments');
        Schema::dropIfExists('broken_stocks');
        Schema::dropIfExists('warranty_replacements');
        Schema::dropIfExists('warranty_cases');
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn('is_main_warehouse');
        });
    }
};
