<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F4.2 FIFO per cabang (ADR-014):
 * - stock_layers: satu lapisan harga per penerimaan per cabang
 *   (qty_received/qty_remaining, unit_cost aktual, tertaut item
 *   penerimaan bila dari receipt).
 * - stock_layer_consumptions: rincian konsumsi lapisan oleh gerakan
 *   keluar (append-only) — sumber HPP & rekonsiliasi persediaan.
 * - stock_movements.unit_cost/total_cost: cap biaya gerakan pada
 *   transaksi pembuatannya (baris tidak diubah sesudah commit).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->decimal('unit_cost', 15, 2)->nullable()->after('qty');
            $table->decimal('total_cost', 15, 2)->nullable()->after('unit_cost');
        });

        Schema::create('stock_layers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('branch_id')->constrained('branches');
            $table->foreignId('receipt_item_id')->nullable()->constrained('receipt_items');
            $table->foreignId('source_movement_id')->nullable()->constrained('stock_movements');
            $table->integer('qty_received');
            $table->integer('qty_remaining');
            $table->decimal('unit_cost', 15, 2);
            $table->boolean('is_opening')->default(false);
            $table->timestamps();

            $table->index(['product_id', 'branch_id', 'qty_remaining', 'id'], 'stock_layers_fifo_idx');
        });

        Schema::create('stock_layer_consumptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('movement_id')->constrained('stock_movements');
            $table->foreignId('layer_id')->constrained('stock_layers');
            $table->integer('qty');
            $table->decimal('unit_cost', 15, 2);
            $table->timestamp('created_at')->nullable();

            $table->index(['movement_id']);
            $table->index(['layer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_layer_consumptions');
        Schema::dropIfExists('stock_layers');
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropColumn(['unit_cost', 'total_cost']);
        });
    }
};
