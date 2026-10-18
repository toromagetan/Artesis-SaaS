<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F2.2 — Perekaman QC per percobaan (Blueprint B.6). Setiap catatan
 * adalah satu percobaan (attempt) untuk satu titik pada satu proses;
 * riwayat TIDAK PERNAH ditimpa — percobaan baru menambah baris.
 * Catatan WAJIB bila hasil fail (ditegakkan service).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qc_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lens_process_id')->constrained('lens_processes')->cascadeOnDelete();
            $table->foreignId('qc_point_template_id')->constrained('qc_point_templates');
            $table->unsignedSmallInteger('attempt')->default(1);
            $table->enum('result', ['pass', 'fail']);
            $table->string('notes', 255)->nullable();
            $table->foreignId('checked_by')->constrained('users');
            $table->timestamp('checked_at');
            $table->timestamps();

            $table->index(['lens_process_id', 'qc_point_template_id', 'attempt'], 'qc_checks_process_point_attempt');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qc_checks');
    }
};
