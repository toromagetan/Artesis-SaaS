<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LNT-005: satu jurnal hanya boleh dibalik SATU kali — ditegakkan
 * basis data lewat indeks unik pada journals.reversal_of_id
 * (NULL tetap boleh banyak: jurnal yang bukan pembalikan).
 * Di belakang kunci baris pada PostingService::reverse().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journals', function (Blueprint $table) {
            $table->unique('reversal_of_id');
        });
    }

    public function down(): void
    {
        Schema::table('journals', function (Blueprint $table) {
            $table->dropUnique(['reversal_of_id']);
        });
    }
};
