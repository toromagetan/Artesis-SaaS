<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Insentif berkala (paritas Artesis): aturan (peran penerima,
 * produk opsional, persen/flat, masa berlaku) → periode per
 * penerima per cabang (draft → disetujui → dibayar) dengan
 * rincian item per aturan + penyesuaian manual ber-alasan.
 * Persetujuan memosting Dr Beban Insentif (5-1800) / Cr Utang
 * Insentif (2-1600); pembayaran melunasinya ke Kas/Bank.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incentive_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('recipient_role', 40);
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('calculation_type', ['percent', 'flat']);
            $table->decimal('value', 15, 2);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('incentive_periods', function (Blueprint $table) {
            $table->id();
            $table->date('period_start');
            $table->date('period_end');
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('recipient_user_id')->constrained('users')->restrictOnDelete();
            $table->enum('status', ['draft', 'approved', 'paid'])->default('draft');
            $table->decimal('transaction_total', 15, 2)->default(0);
            $table->decimal('incentive_total', 15, 2)->default(0);
            $table->foreignId('generated_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('paid_at')->nullable();
            $table->string('pay_method', 20)->nullable();
            $table->timestamps();
            $table->unique(['period_start', 'period_end', 'branch_id', 'recipient_user_id'], 'incentive_period_unique');
        });

        Schema::create('incentive_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incentive_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('incentive_rule_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('basis_amount', 15, 2)->default(0);
            $table->unsignedInteger('basis_qty')->default(0);
            $table->decimal('amount', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('incentive_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incentive_period_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('reason', 200);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incentive_adjustments');
        Schema::dropIfExists('incentive_items');
        Schema::dropIfExists('incentive_periods');
        Schema::dropIfExists('incentive_rules');
    }
};
