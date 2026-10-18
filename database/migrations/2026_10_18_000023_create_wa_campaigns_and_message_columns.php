<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F3.8 — Kampanye WA + kolom inbox pada wa_messages.
 * Kampanye hanya boleh dikirim sesudah disetujui (approved_by) DAN
 * template-nya disetujui — ditegakkan CampaignService.
 * wa_messages.read_at menandai pesan masuk yang sudah dibaca di
 * inbox; campaign_id menautkan pesan hasil kampanye ke kampanyenya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->json('segment');
            $table->foreignId('template_id')->constrained('wa_templates');
            $table->unsignedInteger('recipient_count')->default(0);
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->enum('status', ['draft', 'approved', 'sending', 'done', 'cancelled'])->default('draft');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::table('wa_messages', function (Blueprint $table) {
            $table->timestamp('read_at')->nullable()->after('sent_at');
            $table->foreignId('campaign_id')->nullable()->after('trigger')->constrained('wa_campaigns');
        });
    }

    public function down(): void
    {
        Schema::table('wa_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('campaign_id');
            $table->dropColumn('read_at');
        });
        Schema::dropIfExists('wa_campaigns');
    }
};
