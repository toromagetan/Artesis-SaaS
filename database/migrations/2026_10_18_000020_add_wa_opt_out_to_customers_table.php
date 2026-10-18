<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F3.4 — Bendera opt-out WhatsApp di pelanggan (Blueprint D.9):
 * pelanggan yang menolak pesan promosi/otomatis tidak pernah dikirimi;
 * MessagingService menghormatinya di setiap jalur kirim.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->boolean('wa_opt_out')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('wa_opt_out');
        });
    }
};
