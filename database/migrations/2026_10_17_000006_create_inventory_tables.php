<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inventaris (Blueprint B.4/Inventory): stock_movements — buku besar stok.
 * Baris tidak pernah di-UPDATE/DELETE; saldo resmi = balance_after
 * gerakan terakhir per (produk, cabang).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('branch_id')->constrained();
            $table->enum('reason', [
                'initial_stock', 'sale', 'sale_void', 'receipt', 'purchase_return',
                'transfer_out', 'transfer_in', 'sale_return', 'opname', 'correction',
            ]);
            $table->integer('qty'); // bertanda: masuk +, keluar −
            $table->string('reference_type', 60);
            $table->unsignedBigInteger('reference_id');
            $table->integer('balance_after');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['product_id', 'branch_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
            $table->index(['branch_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
