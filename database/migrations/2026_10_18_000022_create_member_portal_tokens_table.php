<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F3.7 — Token portal member. Hanya HASH (sha256) token yang disimpan;
 * token mentah hanya ada sesaat saat diterbitkan. Token dapat dicabut
 * (revoked_at) dan penerbitan baru mencabut token lama pelanggan itu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_portal_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_portal_tokens');
    }
};
