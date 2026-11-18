<?php

namespace Tests\Feature\Comms;

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
 * Koreksi lanjutan webhook WA (audit independen lanjutan atas
 * PR #86, 18 Nov 2026): penanganan QueryException pada
 * storeIncoming() sebelumnya menelan SEMUA kesalahan basis data
 * sebagai "duplikat" — pesan yang gagal disimpan karena sebab
 * lain akan hilang diam-diam karena webhook tetap menjawab 200.
 * Sekarang hanya pelanggaran unique index incoming_provider_key
 * yang diabaikan; kesalahan lain dilempar kembali. Pemeriksaan
 * duplikat di awal juga dibatasi pada pesan MASUK saja, agar id
 * provider pesan keluar tidak menyingkirkan pesan masuk yang sah.
 */
class WaWebhookErrorHandlingTest extends TestCase
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

    private function gatewayFor(Tenant $tenant, string $phoneNumberId, string $secret, string $sender): WaGateway
    {
        return WaGateway::create([
            'tenant_id' => $tenant->id,
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

    public function test_incoming_message_sharing_provider_id_with_an_outgoing_message_is_stored(): void
    {
        [$tenant, $admin, $branch] = $this->tenantAdmin('a');
        $this->gatewayFor($tenant, 'PN-A', 'secret-a', '628110000001');
        $customer = Customer::create([
            'name' => 'Pelanggan A', 'phone' => '6281234567890',
            'branch_id' => $branch->id, 'tenant_id' => $tenant->id,
        ]);

        // Pesan KELUAR yang sudah tercatat memakai id provider ini
        // (id dicatat sesudah kirim; tabrakan lintas arah mungkin
        // terjadi pada id kiriman provider).
        WaMessage::create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'phone' => '6281234567890',
            'template_key' => null,
            'body' => 'pesan keluar',
            'direction' => 'out',
            'status' => 'sent',
            'trigger' => 'manual',
            'sent_by' => $admin->id,
            'provider_message_id' => 'wamid.SAMA',
            'sent_at' => now(),
        ]);

        // Pesan MASUK dengan id provider yang sama adalah pesan
        // yang berbeda dan WAJIB tersimpan.
        $this->signedWebhookPost(
            $this->incomingPayload('PN-A', '6281234567890', 'wamid.SAMA', 'balasan pelanggan'),
            'secret-a',
        )->assertOk();

        $this->assertSame(2, WaMessage::where('provider_message_id', 'wamid.SAMA')->count());
        $incoming = WaMessage::where('provider_message_id', 'wamid.SAMA')
            ->where('direction', 'in')->firstOrFail();
        $this->assertSame('balasan pelanggan', $incoming->body);
        $this->assertSame($tenant->id, $incoming->tenant_id);
    }

    public function test_non_duplicate_database_error_propagates_instead_of_being_swallowed(): void
    {
        [$tenant, , $branch] = $this->tenantAdmin('a');
        $this->gatewayFor($tenant, 'PN-A', 'secret-a', '628110000001');
        Customer::create([
            'name' => 'Pelanggan A', 'phone' => '6281234567890',
            'branch_id' => $branch->id, 'tenant_id' => $tenant->id,
        ]);

        // Paksa kegagalan basis data NON-duplikat pada sisipan pesan
        // tertentu: trigger menolak baris dengan SIGNAL (bukan
        // pelanggaran unique mana pun). Trigger dibuat lewat
        // koneksi admin TERPISAH (pola adminPdo test konkurensi):
        // CREATE TRIGGER butuh hak SUPER saat binary logging aktif,
        // dan DDL pada koneksi uji akan meng-commit transaksi
        // RefreshDatabase sehingga mengotori test berikutnya.
        $admin = $this->adminPdo();
        $database = (string) config('database.connections.mysql.database');
        $admin->exec('USE `'.$database.'`');
        $admin->exec(
            'CREATE TRIGGER wa_messages_uji_gagal BEFORE INSERT ON wa_messages '
            .'FOR EACH ROW BEGIN '
            ."IF NEW.provider_message_id = 'wamid.BOOM' THEN "
            ."SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'kegagalan basis data paksa (uji)'; "
            .'END IF; END'
        );

        $this->withoutExceptionHandling();
        $thrown = null;
        try {
            $this->signedWebhookPost(
                $this->incomingPayload('PN-A', '6281234567890', 'wamid.BOOM', 'pesan penting'),
                'secret-a',
            );
        } catch (QueryException $e) {
            $thrown = $e;
        } finally {
            $admin->exec('DROP TRIGGER IF EXISTS wa_messages_uji_gagal');
        }

        // Kesalahan BUKAN duplikat harus naik ke pemanggil (webhook
        // gagal → provider mengirim ulang), bukan ditelan menjadi
        // HTTP 200 yang menghilangkan pesan.
        $this->assertNotNull($thrown, 'QueryException non-duplikat tidak boleh ditelan webhook.');
        $this->assertSame(0, WaMessage::where('provider_message_id', 'wamid.BOOM')->count());
    }

    public function test_unique_violation_race_is_treated_as_duplicate_callback(): void
    {
        [$tenant, , $branch] = $this->tenantAdmin('a');
        $this->gatewayFor($tenant, 'PN-A', 'secret-a', '628110000001');
        $customer = Customer::create([
            'name' => 'Pelanggan A', 'phone' => '6281234567890',
            'branch_id' => $branch->id, 'tenant_id' => $tenant->id,
        ]);

        // Simulasikan balapan callback identik secara deterministik:
        // pemeriksaan exists() lolos (belum ada baris), lalu tepat
        // sebelum sisipan webhook dieksekusi, "callback kembaran"
        // menyisipkan baris yang sama lebih dulu — unique index
        // incoming_provider_key menolak sisipan webhook.
        $inserted = false;
        WaMessage::creating(function () use (&$inserted, $tenant, $customer) {
            if ($inserted) {
                return;
            }
            $inserted = true;
            DB::table('wa_messages')->insert([
                'tenant_id' => $tenant->id,
                'customer_id' => $customer->id,
                'phone' => '6281234567890',
                'template_key' => null,
                'body' => 'sisipan callback kembaran',
                'direction' => 'in',
                'status' => 'delivered',
                'trigger' => 'inbox',
                'sent_by' => null,
                'provider_message_id' => 'wamid.BALAPAN',
                'sent_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        // Yang kalah balapan adalah duplikat: webhook tetap 200 dan
        // tepat satu pesan masuk tercatat.
        $this->signedWebhookPost(
            $this->incomingPayload('PN-A', '6281234567890', 'wamid.BALAPAN', 'pesan balapan'),
            'secret-a',
        )->assertOk();

        $this->assertSame(
            1,
            WaMessage::where('provider_message_id', 'wamid.BALAPAN')->where('direction', 'in')->count(),
        );
    }

    /** Koneksi admin untuk DDL uji (pola test konkurensi LNT-007). */
    private function adminPdo(): \PDO
    {
        $socket = '/var/run/mysqld/mysqld.sock';
        if (file_exists($socket)) {
            try {
                return new \PDO('mysql:unix_socket='.$socket, 'root', null);
            } catch (\Throwable) {
                // jatuh ke TCP di bawah
            }
        }

        return new \PDO(
            'mysql:host=127.0.0.1;port='.(string) env('DB_PORT', '3306'),
            (string) env('DB_USERNAME', 'root'),
            (string) env('DB_PASSWORD', ''),
        );
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
