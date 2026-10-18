<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F5.3 Offline kasir (fondasi server) — kunci idempotensi pada
 * penjualan, registri perangkat kasir bertoken, dan antrean
 * pengecualian sinkronisasi untuk resolusi staf.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('client_uuid', 64)->nullable()->unique()->after('number');
        });

        Schema::create('offline_devices', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('branch_id')->constrained('branches');
            $table->foreignId('user_id')->constrained('users');
            $table->string('token_hash', 64)->unique();
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('offline_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offline_device_id')->constrained('offline_devices');
            $table->foreignId('branch_id')->constrained('branches');
            $table->string('client_uuid', 64);
            $table->string('local_no')->nullable();
            $table->json('payload');
            $table->string('reason');
            $table->string('status', 20)->default('pending');
            $table->text('resolution_note')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->unique(['offline_device_id', 'client_uuid']);
            $table->index(['branch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offline_exceptions');
        Schema::dropIfExists('offline_devices');
        Schema::table('sales', function (Blueprint $table) {
            $table->dropUnique(['client_uuid']);
            $table->dropColumn('client_uuid');
        });
    }
};
