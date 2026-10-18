<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F3.2 — Jadwal cicilan piutang pelanggan (Blueprint B.3).
 * Status tersimpan: open/partial/paid. Status 'overdue' pada Blueprint
 * bersifat TERHITUNG (due_date lampau & belum lunas) — tidak disimpan,
 * lihat SaleInstallment::effectiveStatus().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->smallInteger('sequence');
            $table->date('due_date');
            $table->decimal('amount', 15, 2);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->enum('status', ['open', 'partial', 'paid'])->default('open');
            $table->timestamps();
            $table->unique(['sale_id', 'sequence']);
            $table->index(['due_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_installments');
    }
};
