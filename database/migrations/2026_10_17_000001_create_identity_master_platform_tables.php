<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel Identity, Master, dan Platform (Blueprint B.1 + audit_logs B. Platform).
 * Kepemilikan tabel per modul (Blueprint Bagian B): Identity (roles, permissions,
 * role_permission, user_role, user_branch), Master (branches, suppliers,
 * insurances, doctors, settings), Platform (audit_logs).
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---- Master: branches (tanpa kolom kode — keputusan terkunci) ----
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->index();
            $table->text('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ---- Identity: roles & permissions ----
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->string('label', 100);
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('group_name', 50)->index();
            $table->timestamps();
        });

        Schema::create('role_permission', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });

        Schema::create('user_role', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->primary(['user_id', 'role_id']);
        });

        // Cabang yang dikelola pengguna; kosong + peran Owner = akses global.
        Schema::create('user_branch', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->primary(['user_id', 'branch_id']);
        });

        // ---- Master data ----
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('contact_person', 100)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('insurances', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('phone', 30)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('doctors', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('clinic', 150)->nullable();
            $table->string('phone', 30)->nullable();
            $table->decimal('commission_rate', 5, 2)->default(0); // persen
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // ---- Platform: audit_logs (hanya INSERT dari aplikasi) ----
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 80);
            $table->string('subject_type', 60);
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('doctors');
        Schema::dropIfExists('insurances');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('user_branch');
        Schema::dropIfExists('user_role');
        Schema::dropIfExists('role_permission');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('branches');
    }
};
