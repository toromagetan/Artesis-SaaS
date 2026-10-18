<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F3.4 — Komunikasi WhatsApp (Blueprint B.7): gateway, template, log pesan.
 * Kredensial gateway TIDAK PERNAH plaintext: kolom credentials_encrypted
 * memakai cast `encrypted` Laravel di model WaGateway.
 * wa_messages adalah log append-only arah keluar & masuk (F3.8);
 * status 'delivered' diperbarui webhook Meta dari provider_message_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_gateways', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 50)->default('meta_cloud_api');
            $table->string('sender_number', 30);
            $table->text('credentials_encrypted')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        Schema::create('wa_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
            $table->text('body');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('wa_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('phone', 30);
            $table->string('template_key', 60)->nullable();
            $table->text('body');
            $table->enum('direction', ['out', 'in'])->default('out');
            $table->enum('status', ['queued', 'sent', 'failed', 'delivered'])->default('queued');
            $table->string('trigger', 60)->default('manual');
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('provider_message_id', 80)->nullable();
            $table->string('error', 255)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'trigger']);
            $table->index('provider_message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_messages');
        Schema::dropIfExists('wa_templates');
        Schema::dropIfExists('wa_gateways');
    }
};
