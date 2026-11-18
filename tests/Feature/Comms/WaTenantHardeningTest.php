<?php

namespace Tests\Feature\Comms;

use App\Modules\Comms\Http\Controllers\WaInboxController;
use App\Modules\Comms\Models\WaGateway;
use App\Modules\Comms\Models\WaMessage;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use App\Modules\Platform\Models\Plan;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\PlanService;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\SaasSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Pengerasan WA (verifikasi audit independen 18 Nov 2026):
 * (A) FK tenant tabel WA mengikuti kebijakan restrictOnDelete
 * migrasi 000057 — penghapusan tenant tidak boleh mengubah data
 * WA tenant menjadi ember global NULL; (B) query Inbox dibatasi
 * cap tenant pesan itu sendiri, bukan hanya kepemilikan pelanggan;
 * (C) idempotensi webhook ditegakkan unique index atas kunci
 * tergenerasi khusus pesan masuk, dan fallback gateway global
 * pada webhook hanya menerima tanda tangan gateway global untuk
 * nomor yang memang tidak dikenal.
 */
class WaTenantHardeningTest extends TestCase
{
    use RefreshDatabase;

    private User $platform;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, AccountingSeeder::class, SaasSeeder::class]);
        $this->platform = User::where('username', 'platform')->firstOrFail();
    }

    /** @return array{Tenant, User, Branch} */
    private function tenantAdmin(string $tag): array
    {
        $tenant = Tenant::create([
            'name' => 'Optik '.$tag, 'slug' => 'optik-'.$tag, 'email' => $tag.'@uji.id',
        ]);
        app(PlanService::class)->assignPlan(
            $tenant,
            Plan::where('key', 'best_offer')->firstOrFail(),
            null,
            $this->platform,
        );
        $branch = Branch::create(['name' => 'Cabang '.$tag, 'tenant_id' => $tenant->id]);
        $admin = User::create([
            'email_verified_at' => now(),
            'name' => 'Admin '.$tag, 'username' => 'admin-'.$tag,
            'email' => 'admin-'.$tag.'@uji.id', 'password' => bcrypt('rahasia-uji'),
            'is_active' => true, 'is_platform' => false, 'tenant_id' => $tenant->id,
        ]);
        $admin->roles()->attach(Role::where('name', 'Owner')->firstOrFail());
        $admin->branches()->attach($branch);

        return [$tenant, $admin, $branch];
    }

    private function gatewayFor(?Tenant $tenant, string $phoneNumberId, string $secret, string $sender): WaGateway
    {
        return WaGateway::create([
            'tenant_id' => $tenant?->id,
            'provider' => 'meta_cloud_api',
            'sender_number' => $sender,
            'credentials_encrypted' => [
                'phone_number_id' => $phoneNumberId,
                'waba_id' => '11223344',
                'access_token' => 'token-'.$phoneNumberId,
                'verify_token' => 'verify-'.$phoneNumberId,
                'app_secret' => $secret,
                'api_version' => 'v21.0',
            ],
            'is_active' => true,
        ]);
    }

    private function customerOf(Tenant $tenant, Branch $branch, string $phone): Customer
    {
        return Customer::create([
            'name' => 'Pelanggan '.$tenant->slug, 'phone' => $phone,
            'branch_id' => $branch->id, 'tenant_id' => $tenant->id,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function message(array $overrides): WaMessage
    {
        return WaMessage::create(array_merge([
            'tenant_id' => null,
            'customer_id' => null,
            'phone' => '6281200000000',
            'template_key' => null,
            'body' => 'isi pesan',
            'direction' => 'in',
            'status' => 'delivered',
            'trigger' => 'inbox',
            'sent_by' => null,
            'provider_message_id' => null,
            'sent_at' => now(),
        ], $overrides));
    }

    public function test_wa_tenant_foreign_keys_restrict_tenant_deletion(): void
    {
        $rules = DB::table('information_schema.REFERENTIAL_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::connection()->getDatabaseName())
            ->where('REFERENCED_TABLE_NAME', 'tenants')
            ->whereIn('TABLE_NAME', ['wa_gateways', 'wa_messages', 'wa_campaigns'])
            ->pluck('DELETE_RULE', 'TABLE_NAME');

        $this->assertCount(3, $rules);
        foreach (['wa_gateways', 'wa_messages', 'wa_campaigns'] as $table) {
            $this->assertSame('RESTRICT', $rules[$table], "FK tenant {$table} harus RESTRICT");
        }

        // Bukti perilaku: tenant yang HANYA memiliki data WA (tanpa
        // cabang/pengguna yang FK-nya sudah restrict lebih dulu)
        // tetap tidak dapat dihapus — dan gateway-nya tidak berubah
        // menjadi ember global.
        $tenant = Tenant::create([
            'name' => 'Optik Sepi', 'slug' => 'optik-sepi', 'email' => 'sepi@uji.id',
        ]);
        $gateway = $this->gatewayFor($tenant, 'PN-SEPI', 'secret-sepi', '628110000009');

        try {
            DB::table('tenants')->where('id', $tenant->id)->delete();
            $this->fail('Penghapusan tenant berdata WA harus ditolak FK restrict.');
        } catch (QueryException) {
            // Ditolak basis data — perilaku yang diharapkan.
        }

        $this->assertSame($tenant->id, $gateway->fresh()->tenant_id);
    }

    public function test_provider_message_id_is_unique_for_incoming_messages_only(): void
    {
        $this->message(['provider_message_id' => 'wamid.UNIK-1']);

        // Duplikat pesan MASUK dengan id provider yang sama ditolak
        // basis data (kunci tergenerasi incoming_provider_key).
        try {
            $this->message(['provider_message_id' => 'wamid.UNIK-1']);
            $this->fail('Duplikat provider_message_id pada pesan masuk harus ditolak.');
        } catch (QueryException) {
            // Ditolak basis data — perilaku yang diharapkan.
        }

        // Pesan KELUAR tidak dibatasi kunci itu: id provider dicatat
        // sesudah kirim dan bukan subjek idempotensi webhook.
        $this->message(['direction' => 'out', 'provider_message_id' => 'wamid.KELUAR-1']);
        $this->message(['direction' => 'out', 'provider_message_id' => 'wamid.KELUAR-1']);
        $this->assertSame(2, WaMessage::where('provider_message_id', 'wamid.KELUAR-1')->count());
    }

    public function test_duplicate_webhook_callback_stores_exactly_one_message(): void
    {
        [$tenant, , $branch] = $this->tenantAdmin('a');
        $this->gatewayFor($tenant, 'PN-A', 'secret-a', '628110000001');
        $this->customerOf($tenant, $branch, '6281234567890');

        $payload = $this->incomingPayload('PN-A', '6281234567890', 'wamid.DOBEL', 'Halo');
        $this->signedWebhookPost($payload, 'secret-a')->assertOk();
        $this->signedWebhookPost($payload, 'secret-a')->assertOk();

        $this->assertSame(1, WaMessage::where('provider_message_id', 'wamid.DOBEL')->count());
    }

    public function test_tenant_phone_id_signed_with_global_secret_is_rejected_but_unknown_number_uses_global_bucket(): void
    {
        [$tenant] = $this->tenantAdmin('a');
        $this->gatewayFor(null, 'PN-G', 'secret-global', '628110000000');
        $this->gatewayFor($tenant, 'PN-A', 'secret-a', '628110000001');

        // Nomor milik tenant A, tanda tangan gateway global: ditolak
        // seluruhnya — callback tenant tidak dapat menumpang jalur
        // global hanya karena tanda tangan global cocok.
        $this->signedWebhookPost(
            $this->incomingPayload('PN-A', '6281299999999', 'wamid.NUMPANG', 'Numpang'),
            'secret-global',
        )->assertForbidden();
        $this->assertSame(0, WaMessage::where('provider_message_id', 'wamid.NUMPANG')->count());

        // Nomor yang benar-benar tidak dikenal + tanda tangan global
        // yang sah: diterima sebagai ember global (mode mandiri).
        $this->signedWebhookPost(
            $this->incomingPayload('PN-X', '6281299999999', 'wamid.GLOBAL', 'Halo global'),
            'secret-global',
        )->assertOk();
        $global = WaMessage::where('provider_message_id', 'wamid.GLOBAL')->firstOrFail();
        $this->assertNull($global->tenant_id);
        $this->assertNull($global->customer_id);
    }

    public function test_inbox_excludes_messages_whose_tenant_stamp_disagrees_with_the_actor(): void
    {
        [$tenantA, $ownerA, $branchA] = $this->tenantAdmin('a');
        [$tenantB] = $this->tenantAdmin('b');
        $customerA = $this->customerOf($tenantA, $branchA, '6281234567890');

        $own = $this->message([
            'tenant_id' => $tenantA->id, 'customer_id' => $customerA->id, 'body' => 'SEEMBER',
        ]);
        // Baris tak konsisten yang disengaja: pelanggan milik A,
        // cap tenant milik B. Tidak ada jalur tulis normal yang
        // menghasilkan ini — pagar inbox tidak boleh bergantung
        // pada konsistensi itu saja.
        $foreign = $this->message([
            'tenant_id' => $tenantB->id, 'customer_id' => $customerA->id, 'body' => 'CAP-ASING',
        ]);

        $this->actingAs($ownerA);
        $controller = app(WaInboxController::class);

        // Index: percakapan terakhir pelanggan A adalah pesan
        // seembernya (bukan pesan bercap asing yang id-nya lebih
        // baru), dan hitungan belum-terbaca hanya menghitung pesan
        // seember (1, bukan 2).
        $indexData = $controller->index()->getData();
        $this->assertTrue($indexData['conversations']->contains('id', $own->id));
        $this->assertFalse($indexData['conversations']->contains('id', $foreign->id));
        $this->assertSame(1, (int) ($indexData['unread'][$customerA->id] ?? 0));

        // Show: hanya pesan seember yang tampil; pesan asing tidak
        // ikut ditandai terbaca oleh aktor A.
        $showMessages = $controller->show($customerA)->getData()['messages'];
        $this->assertTrue($showMessages->contains('id', $own->id));
        $this->assertFalse($showMessages->contains('id', $foreign->id));
        $this->assertNull($foreign->fresh()->read_at);
        $this->assertNotNull($own->fresh()->read_at);
    }

    private function signedWebhookPost(array $payload, string $secret): TestResponse
    {
        $json = json_encode($payload);

        return $this->call('POST', route('wa-webhook.handle'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $json, $secret),
        ], $json);
    }

    private function incomingPayload(string $phoneNumberId, string $from, string $providerId, string $body): array
    {
        return ['entry' => [['changes' => [['value' => [
            'metadata' => ['phone_number_id' => $phoneNumberId],
            'messages' => [[
                'id' => $providerId, 'from' => $from, 'type' => 'text',
                'text' => ['body' => $body],
            ]],
        ]]]]]];
    }
}
