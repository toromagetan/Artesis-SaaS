<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Akuntansi fondasi (Blueprint B.4/Accounting + E.1): bagan akun,
 * jurnal + baris (Σdebit = Σkredit ditegakkan PostingService), dan
 * kunci periode. Jurnal bersifat append-only dari sisi UI (baca-saja;
 * koreksi hanya lewat jurnal balik).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 120);
            $table->enum('group', ['asset', 'liability', 'equity', 'revenue', 'expense']);
            $table->foreignId('parent_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('journals', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->date('journal_date');
            $table->string('source_type', 60);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('description', 255)->nullable();
            $table->boolean('period_locked')->default(false);
            $table->foreignId('reversal_of_id')->nullable()->constrained('journals')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['source_type', 'source_id']);
            $table->index('journal_date');
        });

        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            $table->string('memo')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'branch_id']);
        });

        Schema::create('period_locks', function (Blueprint $table) {
            $table->id();
            $table->char('period', 7)->unique(); // YYYY-MM
            $table->foreignId('locked_by')->constrained('users');
            $table->timestamp('locked_at');
            $table->foreignId('unlocked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('unlock_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('period_locks');
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journals');
        Schema::dropIfExists('accounts');
    }
};
