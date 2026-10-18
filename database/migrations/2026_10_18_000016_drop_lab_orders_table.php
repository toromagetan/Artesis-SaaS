<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Koreksi owner 2026-10-18: order lab BUKAN dokumen mandiri — pekerjaan
     * lab eksternal berjalan lewat rantai PR -> PO yang sudah ada. Tabel
     * lab_orders (F2.3) dicabut seluruhnya.
     */
    public function up(): void
    {
        Schema::dropIfExists('lab_orders');
    }

    public function down(): void
    {
        // Pencabutan disengaja; tidak ada jalur mundur.
    }
};
