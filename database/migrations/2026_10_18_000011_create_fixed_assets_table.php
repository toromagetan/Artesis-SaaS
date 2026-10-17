<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aset tetap (F1.4, Blueprint B.1): master aset + akumulasi
 * penyusutan garis lurus. Tanpa jalur hapus — aset berhenti
 * lewat status disposed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('acquired_at');
            $table->decimal('acquisition_cost', 15, 2);
            $table->unsignedInteger('useful_life_months');
            $table->enum('method', ['straight_line'])->default('straight_line');
            $table->decimal('accumulated_amount', 15, 2)->default(0);
            $table->enum('status', ['active', 'disposed'])->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_assets');
    }
};
