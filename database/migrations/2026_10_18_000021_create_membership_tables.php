<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F3.6 — Membership & poin (Blueprint B.6).
 * points_ledger adalah buku besar poin APPEND-ONLY: saldo pelanggan
 * = Σ baris. Baris tidak pernah diubah/dihapus; koreksi selalu baris
 * baru (reversal/expire). Karena itu tabel hanya punya created_at.
 * Saldo liabilitas poin di jurnal (akun 2-1200) harus selalu sama
 * dengan Σ poin aktif x nilai tukar (gerbang Fase 3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_levels', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            $table->decimal('min_accumulation', 15, 2)->default(0);
            $table->decimal('point_multiplier', 4, 2)->default(1);
            $table->text('benefits')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('points_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->enum('type', ['earn', 'redeem', 'expire', 'reversal']);
            $table->integer('points');
            $table->string('reference_type', 60)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->date('expires_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['customer_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('points_ledger');
        Schema::dropIfExists('membership_levels');
    }
};
