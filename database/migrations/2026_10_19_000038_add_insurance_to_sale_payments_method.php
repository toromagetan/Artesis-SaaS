<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Metode pembayaran 'insurance' untuk kaki klaim asuransi
 * (diisi SalesService dari insurance_claim_amount, BUKAN pilihan
 * manual kasir — SalePayment::METHODS tidak berubah).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE sale_payments MODIFY method ENUM('cash','debit','qris','transfer','card','points','insurance') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE sale_payments MODIFY method ENUM('cash','debit','qris','transfer','card','points') NOT NULL");
    }
};
