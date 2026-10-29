<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M5 — Penagihan sederhana: permintaan naik paket dari admin tenant
 * yang dibayar via transfer manual lalu dikonfirmasi aktor platform.
 * Jumlah dibekukan saat permintaan dibuat (harga paket boleh berubah
 * sesudahnya tanpa mengubah tagihan yang sudah diajukan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_billing_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('plans');
            $table->unsignedInteger('extra_branches')->default(0);
            $table->unsignedInteger('months')->default(1);
            $table->unsignedBigInteger('amount');
            $table->string('status', 20)->default('pending');
            $table->string('note')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('subscription_id')->nullable()
                ->constrained('tenant_subscriptions')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_billing_requests');
    }
};
