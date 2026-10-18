<?php

namespace Tests\Feature\Platform;

use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Platform\Models\ApiToken;
use App\Modules\Platform\Services\ApiTokenService;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F4.6 API read-only: penerbitan token (hash saja disimpan),
 * verifikasi Bearer + cakupan, 401/403, pencabutan, catatan
 * pemakaian, dan pagar UI api_tokens.manage.
 */
class ApiTokenTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IdentitySeeder::class);
        $this->owner = User::where('username', 'owner')->firstOrFail();
    }

    public function test_token_lifecycle_and_scoped_access(): void
    {
        Product::factory()->create(['sku' => 'API-1', 'name' => 'Produk API']);
        $issued = app(ApiTokenService::class)->issue($this->owner, 'Integrasi Uji', ['products.read']);
        $plain = $issued['plain'];
        $token = $issued['token'];

        // Hanya hash yang tersimpan.
        $this->assertSame(hash('sha256', $plain), $token->token_hash);
        $this->assertSame(64, strlen($token->token_hash));

        // Tanpa token → 401; token salah → 401.
        $this->getJson('/api/v1/products')->assertUnauthorized();
        $this->withHeader('Authorization', 'Bearer token-ngawur')->getJson('/api/v1/products')->assertUnauthorized();

        // Token sah + cakupan cocok → 200 & data terbaca.
        $response = $this->withHeader('Authorization', 'Bearer '.$plain)->getJson('/api/v1/products');
        $response->assertOk()->assertJsonPath('data.0.sku', 'API-1');
        $this->assertNotNull($token->refresh()->last_used_at);

        // Cakupan lain → 403.
        $this->withHeader('Authorization', 'Bearer '.$plain)->getJson('/api/v1/sales')->assertForbidden();
        $this->withHeader('Authorization', 'Bearer '.$plain)->getJson('/api/v1/stock')->assertForbidden();

        // Sesudah dicabut → 401 lagi.
        app(ApiTokenService::class)->revoke($this->owner, $token);
        $this->withHeader('Authorization', 'Bearer '.$plain)->getJson('/api/v1/products')->assertUnauthorized();
    }

    public function test_ui_issue_shows_plain_token_once_and_requires_permission(): void
    {
        $response = $this->actingAs($this->owner)->post(route('api-tokens.store'), [
            'name' => 'Dari UI', 'scopes' => ['stock.read', 'reports.read'],
        ]);
        $response->assertRedirect(route('api-tokens.index'));
        $plain = session('plain_token');
        $this->assertNotNull($plain);
        $this->assertSame(hash('sha256', (string) $plain), ApiToken::firstOrFail()->token_hash);

        $this->actingAs($this->owner)->get(route('api-tokens.index'))
            ->assertOk()->assertSee('Dari UI');

        $kasir = User::factory()->create();
        $kasir->roles()->sync([Role::where('name', 'kasir')->firstOrFail()->id]);
        $this->actingAs($kasir)->get(route('api-tokens.index'))->assertForbidden();
    }
}
