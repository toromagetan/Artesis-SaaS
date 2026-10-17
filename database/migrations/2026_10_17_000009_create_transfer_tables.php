<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Transfer distribusi (Blueprint B.4/Inventory): stok berkurang di
 * asal saat sent (transfer_out), bertambah di tujuan saat received
 * (transfer_in); selisih waktunya adalah stok dalam perjalanan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('from_branch_id')->constrained('branches');
            $table->foreignId('to_branch_id')->constrained('branches');
            $table->enum('source_type', ['purchase_order', 'branch_stock']);
            $table->unsignedBigInteger('source_id')->nullable(); // PO asal bila dari PO
            $table->enum('status', ['sent', 'received', 'cancelled'])->default('sent');
            $table->foreignId('sent_by')->constrained('users');
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['from_branch_id', 'status']);
            $table->index(['to_branch_id', 'status']);
        });

        Schema::create('stock_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->integer('qty');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_items');
        Schema::dropIfExists('stock_transfers');
    }
};
