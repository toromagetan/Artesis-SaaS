<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LNT-004 (keputusan owner 2026-10-27): token API dapat diberi masa
 * berlaku. Kolom nullable — token lama tanpa kedaluwarsa tetap sah
 * sampai dicabut (kompatibilitas mundur).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_tokens', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->after('revoked_at');
        });
    }

    public function down(): void
    {
        Schema::table('api_tokens', function (Blueprint $table) {
            $table->dropColumn('expires_at');
        });
    }
};
