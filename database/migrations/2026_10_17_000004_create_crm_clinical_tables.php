<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CRM & Clinical (Blueprint B.2): customers + prescriptions.
 * membership_level_id disiapkan sebagai kolom polos — kunci asingnya
 * ditambahkan modul Membership (Fase 3) saat tabelnya lahir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('phone', 30);
            $table->string('email', 150)->nullable();
            $table->text('address')->nullable();
            $table->date('birth_date')->nullable();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('insurance_id')->nullable()->constrained();
            $table->unsignedBigInteger('membership_level_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('phone');
            $table->index('name');
            $table->index(['branch_id', 'is_active']);
        });

        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('branch_id')->constrained();
            $table->date('examined_at');
            $table->foreignId('examiner_id')->nullable()->constrained('users')->nullOnDelete();

            // Mata kanan (OD) & kiri (OS) — PDR/PDL HANYA di sini (B.2).
            $table->decimal('od_sph', 5, 2)->default(0);
            $table->decimal('od_cyl', 5, 2)->default(0);
            $table->smallInteger('od_axis')->nullable();
            $table->decimal('od_add', 5, 2)->nullable();
            $table->decimal('od_pdr', 4, 1)->nullable();
            $table->decimal('os_sph', 5, 2)->default(0);
            $table->decimal('os_cyl', 5, 2)->default(0);
            $table->smallInteger('os_axis')->nullable();
            $table->decimal('os_add', 5, 2)->nullable();
            $table->decimal('os_pdl', 4, 1)->nullable();

            $table->decimal('pd_total', 4, 1)->nullable();
            $table->string('prism', 30)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'examined_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescriptions');
        Schema::dropIfExists('customers');
    }
};
