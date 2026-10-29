<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M4 — Penegakan paket: produk dicap tenant pemiliknya agar batas
 * products_max (Free 500) dapat dihitung per tenant. Nullable —
 * produk instalasi lama (satu bisnis) tetap sah tanpa cap; produk
 * baru dicap dari tenant aktor pembuatnya oleh ProductService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')
                ->constrained('tenants')->nullOnDelete();
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
        });
    }
};
