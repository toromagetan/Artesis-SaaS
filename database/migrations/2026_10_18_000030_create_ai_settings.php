<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F5.1 Asisten AI bisnis — konfigurasi penyedia model AI milik
 * pemilik (endpoint kompatibel OpenAI + API key terenkripsi +
 * nama model), diatur lewat UI Pengaturan (keputusan owner
 * 2026-10-18). Satu baris tunggal (singleton).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_settings', function (Blueprint $table) {
            $table->id();
            $table->string('base_url')->default('https://api.openai.com/v1');
            $table->string('model')->default('gpt-4o-mini');
            $table->text('credentials_encrypted')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_settings');
    }
};
