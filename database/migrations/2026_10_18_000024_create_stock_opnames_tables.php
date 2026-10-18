<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F4.1 Opname terjadwal (Blueprint B.4 + PRD-Detail 7.4):
 * stock_opnames (jadwal per cabang, status scheduled → counting →
 * review → approved; counting → cancelled oleh Owner) dan
 * stock_opname_items (snapshot system_qty saat hitung dimulai,
 * counted_qty hasil hitung fisik, difference terhitung, movement_id
 * gerakan 'opname' yang terbit saat approval).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_opnames', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches');
            $table->date('scheduled_date');
            $table->enum('status', ['scheduled', 'counting', 'review', 'approved', 'cancelled'])->default('scheduled');
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status']);
        });

        Schema::create('stock_opname_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_opname_id')->constrained('stock_opnames')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->integer('system_qty');
            $table->integer('counted_qty')->nullable();
            $table->integer('difference')->default(0);
            $table->foreignId('movement_id')->nullable()->constrained('stock_movements');
            $table->timestamps();

            $table->unique(['stock_opname_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_opname_items');
        Schema::dropIfExists('stock_opnames');
    }
};
