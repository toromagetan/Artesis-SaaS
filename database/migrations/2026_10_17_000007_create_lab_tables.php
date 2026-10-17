<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lab (Blueprint B.4/Lab): lens_processes — satu proses per penjualan
 * (sale_id UNIQUE). Status done HANYA lewat LensProcessService::complete().
 * Tabel QC dan order lab menyusul di Fase 2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lens_processes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->unique()->constrained();
            $table->enum('status', ['waiting', 'edging', 'qc', 'ready', 'done'])->default('waiting');
            $table->foreignId('technician_id')->nullable()->constrained('users')->nullOnDelete();
            $table->smallInteger('estimated_days')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lens_processes');
    }
};
