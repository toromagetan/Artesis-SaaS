<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * B-01: kolom verifikasi email untuk pengguna. Sengaja nullable
     * tanpa nilai bawaan: pengguna lama bernilai NULL (belum
     * terverifikasi) — gerbang verifikasi hanya berlaku bagi
     * pengguna tenant yang MEMILIKI email (lihat EnsureEmailVerified),
     * sehingga akun platform, standalone, dan tanpa email tidak
     * terkunci oleh migrasi ini.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('email_verified_at')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('email_verified_at');
        });
    }
};
