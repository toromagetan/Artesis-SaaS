<?php

namespace Tests\Feature\Security;

use App\Modules\Identity\Models\User;
use App\Modules\Platform\Models\Tenant;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\SaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * S-05 — Anti-penyalahgunaan pendaftaran publik: Cloudflare
 * Turnstile pada POST /daftar. Kontrak yang dibuktikan:
 * - tanpa kunci terkonfigurasi, fitur nonaktif anggun dan alur
 *   pendaftaran berjalan seperti semula;
 * - begitu aktif, token hilang/ditolak Cloudflare/jaringan gagal
 *   → pendaftaran DITOLAK (gagal-tertutup) dan tidak ada tenant,
 *   cabang, atau pengguna yang tercipta;
 * - token terverifikasi → pendaftaran berjalan normal;
 * - widget hanya dirender saat fitur aktif.
 */
class SignupAntiBotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Peran Owner + paket bawaan yang dibutuhkan alur pendaftaran.
        $this->seed([IdentitySeeder::class, SaasSeeder::class]);
    }

    /** @return array<string, string> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'business_name' => 'Optik Anti Bot',
            'owner_name' => 'Pemilik Uji',
            'email' => 'antibot@uji.id',
            'branch_name' => 'Cabang Uji',
            'password' => 'Rahasia-uji-123',
            'password_confirmation' => 'Rahasia-uji-123',
        ], $overrides);
    }

    private function enableTurnstile(): void
    {
        config([
            'services.turnstile.site_key' => 'site-key-uji',
            'services.turnstile.secret' => 'secret-uji',
        ]);
    }

    public function test_signup_works_without_turnstile_configured(): void
    {
        $tenantsBefore = Tenant::count();
        $this->post(route('signup.store'), $this->payload())
            ->assertRedirect(route('dashboard'));

        $this->assertSame($tenantsBefore + 1, Tenant::count());
        $this->assertAuthenticated();
    }

    public function test_signup_form_renders_widget_only_when_enabled(): void
    {
        $this->get(route('signup.create'))->assertOk()->assertDontSee('cf-turnstile');

        $this->enableTurnstile();
        $this->get(route('signup.create'))
            ->assertOk()
            ->assertSee('cf-turnstile')
            ->assertSee('site-key-uji');
    }

    public function test_signup_rejected_without_token_when_enabled(): void
    {
        $this->enableTurnstile();
        $tenantsBefore = Tenant::count();
        $usersBefore = User::count();

        $this->post(route('signup.store'), $this->payload())
            ->assertSessionHasErrors('cf-turnstile-response');

        $this->assertSame($tenantsBefore, Tenant::count());
        $this->assertSame($usersBefore, User::count());
        $this->assertGuest();
    }

    public function test_signup_rejected_when_cloudflare_denies_token(): void
    {
        $this->enableTurnstile();
        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => false], 200),
        ]);

        $tenantsBefore = Tenant::count();
        $this->post(route('signup.store'), $this->payload(['cf-turnstile-response' => 'token-buruk']))
            ->assertSessionHasErrors('cf-turnstile-response');

        $this->assertSame($tenantsBefore, Tenant::count());
        $this->assertGuest();
    }

    public function test_signup_fails_closed_when_verification_unreachable(): void
    {
        $this->enableTurnstile();
        Http::fake(function () {
            throw new ConnectionException('network down');
        });

        $tenantsBefore = Tenant::count();
        $this->post(route('signup.store'), $this->payload(['cf-turnstile-response' => 'token-apa-pun']))
            ->assertSessionHasErrors('cf-turnstile-response');

        $this->assertSame($tenantsBefore, Tenant::count());
        $this->assertGuest();
    }

    public function test_signup_succeeds_with_verified_token(): void
    {
        $this->enableTurnstile();
        Http::fake([
            'challenges.cloudflare.com/*' => Http::response([
                'success' => true, 'action' => 'signup', 'hostname' => 'localhost',
            ], 200),
        ]);

        $tenantsBefore = Tenant::count();
        $this->post(route('signup.store'), $this->payload(['cf-turnstile-response' => 'token-sah']))
            ->assertRedirect(route('dashboard'));

        $this->assertSame($tenantsBefore + 1, Tenant::count());
        $this->assertAuthenticated();
        Http::assertSent(fn ($request) => str_contains($request->url(), 'siteverify')
            && $request['response'] === 'token-sah'
            && $request['secret'] === 'secret-uji');
    }

    public function test_partial_configuration_site_key_only_denies_signup(): void
    {
        config(['services.turnstile.site_key' => 'site-key-uji', 'services.turnstile.secret' => null]);
        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => true, 'action' => 'signup', 'hostname' => 'localhost'], 200),
        ]);

        $tenantsBefore = Tenant::count();
        $this->post(route('signup.store'), $this->payload(['cf-turnstile-response' => 'token-apa-pun']))
            ->assertSessionHasErrors('cf-turnstile-response');

        $this->assertSame($tenantsBefore, Tenant::count());
        $this->assertGuest();
    }

    public function test_partial_configuration_secret_only_denies_signup(): void
    {
        config(['services.turnstile.site_key' => null, 'services.turnstile.secret' => 'secret-uji']);

        $tenantsBefore = Tenant::count();
        $this->post(route('signup.store'), $this->payload(['cf-turnstile-response' => 'token-apa-pun']))
            ->assertSessionHasErrors('cf-turnstile-response');

        $this->assertSame($tenantsBefore, Tenant::count());
        $this->assertGuest();
    }

    public function test_signup_rejected_when_action_mismatches(): void
    {
        $this->enableTurnstile();
        Http::fake([
            'challenges.cloudflare.com/*' => Http::response([
                'success' => true, 'action' => 'login', 'hostname' => 'localhost',
            ], 200),
        ]);

        $tenantsBefore = Tenant::count();
        $this->post(route('signup.store'), $this->payload(['cf-turnstile-response' => 'token-sah']))
            ->assertSessionHasErrors('cf-turnstile-response');

        $this->assertSame($tenantsBefore, Tenant::count());
    }

    public function test_signup_rejected_when_hostname_not_trusted(): void
    {
        $this->enableTurnstile();
        Http::fake([
            'challenges.cloudflare.com/*' => Http::response([
                'success' => true, 'action' => 'signup', 'hostname' => 'situs-lain.example',
            ], 200),
        ]);

        $tenantsBefore = Tenant::count();
        $this->post(route('signup.store'), $this->payload(['cf-turnstile-response' => 'token-sah']))
            ->assertSessionHasErrors('cf-turnstile-response');

        $this->assertSame($tenantsBefore, Tenant::count());
    }

    public function test_signup_accepts_hostname_from_configured_allowlist(): void
    {
        $this->enableTurnstile();
        config(['services.turnstile.hostnames' => ['app.example.id']]);
        Http::fake([
            'challenges.cloudflare.com/*' => Http::response([
                'success' => true, 'action' => 'signup', 'hostname' => 'app.example.id',
            ], 200),
        ]);

        $tenantsBefore = Tenant::count();
        $this->post(route('signup.store'), $this->payload(['cf-turnstile-response' => 'token-sah']))
            ->assertRedirect(route('dashboard'));

        $this->assertSame($tenantsBefore + 1, Tenant::count());
    }

    public function test_array_shaped_token_is_a_validation_error_not_a_500(): void
    {
        $this->enableTurnstile();

        $response = $this->post(route('signup.store'), $this->payload(['cf-turnstile-response' => ['bentuk-salah']]));
        $response->assertSessionHasErrors('cf-turnstile-response');
        $this->assertNotSame(500, $response->status());
        $this->assertGuest();
    }

    public function test_signup_rejected_when_no_trusted_hostname_can_be_determined(): void
    {
        $this->enableTurnstile();
        // APP_URL tanpa hostname valid + daftar eksplisit kosong:
        // tidak ada hostname tepercaya → verifikasi wajib gagal.
        config(['app.url' => 'tanpa-host', 'services.turnstile.hostnames' => []]);
        Http::fake([
            'challenges.cloudflare.com/*' => Http::response([
                'success' => true, 'action' => 'signup', 'hostname' => 'localhost',
            ], 200),
        ]);

        $tenantsBefore = Tenant::count();
        $this->post(route('signup.store'), $this->payload(['cf-turnstile-response' => 'token-sah']))
            ->assertSessionHasErrors('cf-turnstile-response');

        $this->assertSame($tenantsBefore, Tenant::count());
    }

    public function test_signup_rejected_when_response_omits_hostname(): void
    {
        $this->enableTurnstile();
        Http::fake([
            'challenges.cloudflare.com/*' => Http::response([
                'success' => true, 'action' => 'signup',
            ], 200),
        ]);

        $tenantsBefore = Tenant::count();
        $this->post(route('signup.store'), $this->payload(['cf-turnstile-response' => 'token-sah']))
            ->assertSessionHasErrors('cf-turnstile-response');

        $this->assertSame($tenantsBefore, Tenant::count());
    }

    public function test_oversized_token_rejected_before_any_network_call(): void
    {
        $this->enableTurnstile();
        Http::fake();

        $this->post(route('signup.store'), $this->payload(['cf-turnstile-response' => str_repeat('a', 2049)]))
            ->assertSessionHasErrors('cf-turnstile-response');

        Http::assertNothingSent();
        $this->assertGuest();
    }
}
