<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penyelarasan M4 dengan Artesis (keputusan owner 2026-10-20):
 * mesin insentif Artesis bekerja dengan PERIODE sebagai wadah
 * (bulan kalender + scope_key cabang/penerima), ITEM sebagai
 * snapshot per baris SaleItem per penerima (penjual + kepala
 * optik), dan ADJUSTMENT sebagai clawback retur ke periode
 * berikutnya. Bentuk lama Lentera (item per aturan per periode
 * penerima, penyesuaian manual) digantikan:
 * - incentive_periods: recipient_user_id menjadi nullable,
 *   tambah scope_key + unique (period_start, period_end,
 *   scope_key); kolom pay_method dicabut (markPaid status-saja,
 *   tanpa jurnal — Artesis tidak menjurnal insentif).
 * - incentive_items & incentive_adjustments dibentuk ulang
 *   sesuai snapshot Artesis (data M4 versi pertama dibuang —
 *   belum ada instalasi produksi).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('incentive_adjustments');
        Schema::dropIfExists('incentive_items');

        Schema::table('incentive_periods', function (Blueprint $table) {
            $table->dropUnique('incentive_period_unique');
            $table->dropColumn('pay_method');
        });
        Schema::table('incentive_periods', function (Blueprint $table) {
            $table->string('scope_key', 60)->default('all')->after('branch_id');
            $table->unique(['period_start', 'period_end', 'scope_key'], 'incentive_period_scope_unique');
        });
        // recipient_user_id menjadi nullable (periode wadah cabang).
        Schema::table('incentive_periods', function (Blueprint $table) {
            $table->foreignId('recipient_user_id')->nullable()->change();
        });

        Schema::create('incentive_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incentive_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            $table->foreignId('sale_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name', 200)->nullable();
            $table->foreignId('recipient_user_id')->constrained('users')->restrictOnDelete();
            $table->string('recipient_role', 40);
            $table->foreignId('incentive_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->string('calculation_type', 20)->nullable();
            $table->decimal('rule_value', 15, 4)->default(0);
            $table->unsignedInteger('quantity')->default(0);
            $table->decimal('item_subtotal', 15, 2)->default(0);
            $table->decimal('item_final_amount', 15, 2)->default(0);
            $table->decimal('transaction_amount', 15, 2)->default(0);
            $table->decimal('incentive_amount', 15, 2)->default(0);
            $table->string('status', 20)->default('eligible');
            $table->string('cancellation_reason', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('incentive_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incentive_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('original_incentive_item_id')->constrained('incentive_items')->restrictOnDelete();
            $table->foreignId('sale_return_id')->constrained()->restrictOnDelete();
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            $table->foreignId('sale_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name', 200)->nullable();
            $table->foreignId('recipient_user_id')->constrained('users')->restrictOnDelete();
            $table->string('recipient_role', 40);
            $table->unsignedInteger('returned_quantity')->default(0);
            $table->string('adjustment_type', 40)->default('customer_return');
            $table->decimal('amount', 15, 2);
            $table->string('reason', 500);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['original_incentive_item_id', 'sale_return_id'], 'incentive_adjustment_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incentive_adjustments');
        Schema::dropIfExists('incentive_items');

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

        Schema::table('incentive_periods', function (Blueprint $table) {
            $table->dropUnique('incentive_period_scope_unique');
            $table->dropColumn('scope_key');
            $table->string('pay_method', 20)->nullable();
            $table->unique(['period_start', 'period_end', 'branch_id', 'recipient_user_id'], 'incentive_period_unique');
        });
    }
};
