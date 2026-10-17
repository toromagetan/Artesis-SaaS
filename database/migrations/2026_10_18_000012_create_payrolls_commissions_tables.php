<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F1.7 Payroll & komisi (Blueprint B.6): payrolls per karyawan per
 * periode (net terhitung di service, terjurnal sekali per periode);
 * commissions terakru dari data final — dokter dari penjualan
 * ber-dokter perujuk, teknisi dari proses lensa Complete; koreksi
 * void = baris penyeimbang (corrects_id), bukan edit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_user_id')->constrained('users');
            $table->char('period', 7);
            $table->decimal('base_amount', 15, 2);
            $table->decimal('allowance_amount', 15, 2)->default(0);
            $table->decimal('deduction_amount', 15, 2)->default(0);
            $table->decimal('net_amount', 15, 2);
            $table->foreignId('journal_id')->nullable()->constrained('journals')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['employee_user_id', 'period']);
        });

        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['doctor', 'technician', 'sales']);
            $table->foreignId('beneficiary_doctor_id')->nullable()->constrained('doctors')->nullOnDelete();
            $table->foreignId('beneficiary_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('source_sale_id')->constrained('sales');
            $table->foreignId('corrects_id')->nullable()->constrained('commissions')->nullOnDelete();
            $table->decimal('base_amount', 15, 2);
            $table->decimal('rate', 5, 2);
            $table->decimal('amount', 15, 2);
            $table->char('period', 7);
            $table->enum('status', ['accrued', 'corrected', 'paid'])->default('accrued');
            $table->foreignId('journal_id')->nullable()->constrained('journals')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['period', 'status']);
            $table->index('source_sale_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commissions');
        Schema::dropIfExists('payrolls');
    }
};
