<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Katalog (Blueprint B.2): products + product_attributes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 50)->unique();
            $table->enum('type', ['frame', 'lens', 'rx', 'softlens', 'accessory']);
            $table->string('name', 150);
            $table->string('brand', 80)->nullable();
            $table->string('color', 50)->nullable();
            $table->decimal('cost_price', 15, 2)->default(0);
            $table->decimal('sale_price', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['type', 'is_active']);
            $table->index('name');
        });

        Schema::create('product_attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('key', 60);
            $table->string('value', 150);
            $table->timestamps();

            $table->unique(['product_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_attributes');
        Schema::dropIfExists('products');
    }
};
