<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F2.1 — Template titik QC faset (Blueprint B.6): daftar titik
 * pemeriksaan yang dapat dikelola (nama, wajib/tidak, urutan, aktif).
 * Bawaan 13 titik ditanam QcPointTemplateSeeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qc_point_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->boolean('is_required')->default(true);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qc_point_templates');
    }
};
