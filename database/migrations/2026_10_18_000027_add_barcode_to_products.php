<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F4.4 Label barcode: kode batang produk (EAN-13 bila 13 digit
 * numerik, selain itu dicetak Code 128; kosong = Code 128 dari SKU).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('barcode', 50)->nullable()->unique()->after('sku');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('barcode');
        });
    }
};
