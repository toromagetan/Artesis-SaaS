<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengerasan WA (verifikasi audit independen 18 Nov 2026) —
 * idempotensi webhook di tingkat basis data. WaWebhookController
 * menjaga duplikasi pesan MASUK dengan pola periksa-exists-lalu-
 * buat: dua callback identik yang tiba bersamaan dapat sama-sama
 * lolos pemeriksaan sebelum salah satunya menyimpan, menghasilkan
 * pesan ganda.
 *
 * Penegakannya SENGAJA hanya untuk pesan masuk: kolom tergenerasi
 * incoming_provider_key bernilai provider_message_id hanya bila
 * direction = 'in' (selain itu NULL), dan unique index atasnya
 * menolak duplikat masuk sementara NULL dikecualikan MySQL.
 * Pesan keluar tidak dibatasi kolom ini — id provider pada pesan
 * keluar dicatat sesudah pengiriman dan penegakan duplikatnya
 * bukan kebutuhan idempotensi webhook; indeks biasa
 * provider_message_id tetap ada untuk pencarian status webhook.
 * Pelanggaran unik saat callback balapan ditangani controller
 * sebagai duplikat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wa_messages', function (Blueprint $table) {
            $table->string('incoming_provider_key', 80)->nullable()
                ->virtualAs("case when direction = 'in' then provider_message_id else null end");
            $table->unique('incoming_provider_key');
        });
    }

    public function down(): void
    {
        Schema::table('wa_messages', function (Blueprint $table) {
            $table->dropUnique(['incoming_provider_key']);
            $table->dropColumn('incoming_provider_key');
        });
    }
};
