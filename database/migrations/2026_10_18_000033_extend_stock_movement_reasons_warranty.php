<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Modul Garansi: tambah tiga alasan gerakan stok ke enum
 * stock_movements.reason — warranty_return (barang lama baik
 * kembali ke stok), warranty_out (pengganti keluar dari gudang),
 * warranty_in (pengganti masuk di cabang). Matriks alasan
 * Blueprint direvisi dari 10 menjadi 13 bersama modul ini.
 */
return new class extends Migration
{
    private const WITH_WARRANTY = "'initial_stock','sale','sale_void','receipt','purchase_return','transfer_out','transfer_in','sale_return','opname','correction','warranty_return','warranty_out','warranty_in'";

    private const ORIGINAL = "'initial_stock','sale','sale_void','receipt','purchase_return','transfer_out','transfer_in','sale_return','opname','correction'";

    public function up(): void
    {
        DB::statement('ALTER TABLE stock_movements MODIFY reason ENUM('.self::WITH_WARRANTY.') NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE stock_movements MODIFY reason ENUM('.self::ORIGINAL.') NOT NULL');
    }
};
