<?php

namespace Tests\Feature\Platform;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use App\Modules\Platform\Models\AiCreditRequest;
use App\Modules\Platform\Models\AiSetting;
use App\Modules\Platform\Models\Plan;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\AiCreditService;
use App\Modules\Platform\Services\PlanService;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\SaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * AI bayar per penggunaan (keputusan owner 2026-11-14): saldo
 * pertanyaan prabayar per tenant — gerbang saldo sebelum bertanya,
 * potongan hanya untuk jawaban berhasil, isi ulang lewat konfirmasi
 * platform, dan konfigurasi kunci platform tertutup bagi tenant.
 */
class AiCreditTest extends TestCase
{
    use RefreshDatabase;

    private User $platform;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, AccountingSeeder::class, SaasSeeder::class]);
        $this->platform = User::where('username', 'platform')->firstOrFail();

        // Konfigurasi penyedia AI milik platform (seperti produksi).
        AiSetting::current()->update([
            'base_url' => 'https://ai.example.test/v1',
            'model' => 'model-uji',
            'credentials_encrypted' => ['api_key' => 'sk-platform-1234'],
            'is_active' => true,
        ]);
        // Satu fake dinamis: pertanyaan berisi "puisi" dijawab
        // penyedia sebagai intent unknown, sisanya sales_total.
        Http::fake([
            'ai.example.test/*' => function ($request) {
                $intent = str_contains((string) $request->body(), 'puisi') ? 'unknown' : 'sales_total';
                $content = $intent === 'unknown'
                    ? '{"intent":"unknown","params":{}}'
                    : '{"intent":"sales_total","params":{"period":"current_month"}}';

                return Http::response(['choices' => [['message' => ['content' => $content]]]], 200);
            },
        ]);
    }

    /** @return array{Tenant, User, Branch} */
    private function tenantOwner(string $tag, string $planKey = 'best_offer'): array
    {
        $tenant = Tenant::create([
            'name' => 'Optik '.$tag, 'slug' => 'optik-'.$tag, 'email' => $tag.'@uji.id',
        ]);
        app(PlanService::class)->assignPlan(
            $tenant,
            Plan::where('key', $planKey)->firstOrFail(),
            null,
            $this->platform,
        );
        $branch = Branch::create(['name' => 'Cabang '.$tag, 'tenant_id' => $tenant->id]);
        $owner = User::create([
            'email_verified_at' => now(),
            'name' => 'Owner '.$tag, 'username' => 'owner-'.$tag,
            'email' => 'owner-'.$tag.'@uji.id', 'password' => bcrypt('rahasia-uji'),
            'is_active' => true, 'is_platform' => false, 'tenant_id' => $tenant->id,
        ]);
        $owner->roles()->attach(Role::where('name', 'Owner')->firstOrFail());
        $owner->branches()->attach($branch);

        return [$tenant, $owner, $branch];
    }

    private function giveCredit(Tenant $tenant, int $questions): void
    {
        $service = app(AiCreditService::class);
        $request = $service->requestTopup($tenant, $questions, $this->platform);
        $service->confirm($request, $this->platform);
    }

    public function test_tenant_without_credit_is_stopped_before_provider_call(): void
    {
        [, $owner] = $this->tenantOwner('a');

        $this->actingAs($owner)->post(route('assistant.ask'), ['question' => 'Berapa omzet bulan ini?'])
            ->assertRedirect(route('assistant.index'));

        $this->assertSame('ai_no_credit', session('ai_result')['status']);
        Http::assertNothingSent();
    }

    public function test_successful_answer_charges_one_credit_and_unknown_is_free(): void
    {
        [$tenant, $owner] = $this->tenantOwner('a');
        $this->giveCredit($tenant, 10);
        $service = app(AiCreditService::class);
        $this->assertSame(10, $service->balance($tenant));

        $this->actingAs($owner)->post(route('assistant.ask'), ['question' => 'Berapa omzet bulan ini?'])
            ->assertRedirect(route('assistant.index'));
        $this->assertSame('ok', session('ai_result')['status']);
        $this->assertSame(9, $service->balance($tenant));

        // Intent di luar daftar putih: tidak dipotong.
        $this->actingAs($owner)->post(route('assistant.ask'), ['question' => 'Tulis puisi untuk saya'])
            ->assertRedirect(route('assistant.index'));
        $this->assertSame(9, $service->balance($tenant));
    }

    public function test_topup_flow_freezes_price_and_double_confirm_is_rejected(): void
    {
        [$tenant, $owner] = $this->tenantOwner('a');
        $service = app(AiCreditService::class);

        // Harga baku 250/pertanyaan; pengajuan membekukan total.
        $this->actingAs($owner)->post(route('ai-credits.store'), ['questions' => 100])
            ->assertRedirect(route('plans.my'));
        $request = AiCreditRequest::where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame(250, $request->unit_price);
        $this->assertSame(25000, $request->amount);
        $this->assertSame(0, $service->balance($tenant));

        // Pengajuan kedua saat masih menunggu → ditolak validasi.
        $this->actingAs($owner)->post(route('ai-credits.store'), ['questions' => 50])
            ->assertSessionHasErrors('questions');

        // Platform mengonfirmasi → saldo bertambah tepat satu kali.
        $this->actingAs($this->platform)->post(route('platform.ai-credits.confirm', $request))
            ->assertRedirect(route('platform.billing.index'));
        $this->assertSame(100, $service->balance($tenant));

        $this->actingAs($this->platform)->post(route('platform.ai-credits.confirm', $request))
            ->assertSessionHasErrors('status');
        $this->assertSame(100, $service->balance($tenant));
    }

    public function test_balances_are_isolated_between_tenants(): void
    {
        [$tenantA, $ownerA] = $this->tenantOwner('a');
        [$tenantB] = $this->tenantOwner('b');
        $this->giveCredit($tenantA, 10);
        $service = app(AiCreditService::class);

        $this->actingAs($ownerA)->post(route('assistant.ask'), ['question' => 'Berapa omzet bulan ini?']);

        $this->assertSame(9, $service->balance($tenantA));
        $this->assertSame(0, $service->balance($tenantB));
    }

    public function test_tenant_owner_cannot_edit_platform_ai_configuration(): void
    {
        [, $owner] = $this->tenantOwner('a');

        $this->actingAs($owner)->get(route('ai-setting.edit'))
            ->assertRedirect(route('plans.my'));
        $this->actingAs($owner)->put(route('ai-setting.update'), [
            'base_url' => 'https://penyusup.example/v1', 'model' => 'x', 'is_active' => '1',
        ])->assertForbidden();

        $this->assertSame('https://ai.example.test/v1', AiSetting::current()->base_url);
    }

    public function test_my_plan_shows_balance_and_platform_price_is_configurable(): void
    {
        [$tenant, $owner] = $this->tenantOwner('a');
        $this->giveCredit($tenant, 25);

        $this->actingAs($owner)->get(route('plans.my'))
            ->assertOk()
            ->assertSee(__('ui.ai_credits.title'))
            ->assertSee('25');

        // Platform mengubah harga → pengajuan berikutnya memakai harga baru.
        $this->actingAs($this->platform)->put(route('platform.billing.settings'), [
            'ai_price_per_question' => 500,
        ])->assertRedirect();
        $this->assertSame(500, app(AiCreditService::class)->pricePerQuestion());
    }
}
