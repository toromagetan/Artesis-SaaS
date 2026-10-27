<?php

namespace Tests\Feature\Platform;

use App\Modules\Catalog\Models\Product;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Platform\Models\ApiToken;
use App\Modules\Platform\Services\ApiTokenService;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * LNT-004 (keputusan owner 2026-10-27, rekomendasi B-ringkas):
 * data sensitif keluar dari cakupan baca umum — harga modal hanya
 * untuk token bercakupan products.cost, nomor HP hanya untuk
 * customers.contact — dan token dapat diberi masa berlaku
 * (expires_at opsional; token lama tanpa kedaluwarsa tetap sah).
 */
class ApiTokenHardeningTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IdentitySeeder::class);
        $this->owner = User::where('username', 'owner')->firstOrFail();
    }

    private function bearer(string $plain): array
    {
        return ['Authorization' => 'Bearer '.$plain];
    }

    public function test_cost_price_requires_products_cost_scope(): void
    {
        Product::factory()->create(['sku' => 'COST-1', 'cost_price' => '75000', 'sale_price' => '150000']);
        $service = app(ApiTokenService::class);

        $readOnly = $service->issue($this->owner, 'Baca Saja', ['products.read']);
        $response = $this->withHeaders($this->bearer($readOnly['plain']))->getJson('/api/v1/products');
        $response->assertOk()
            ->assertJsonPath('data.0.sale_price', '150000.00')
            ->assertJsonMissingPath('data.0.cost_price');

        $withCost = $service->issue($this->owner, 'Dengan Modal', ['products.read', 'products.cost']);
        $response = $this->withHeaders($this->bearer($withCost['plain']))->getJson('/api/v1/products');
        $response->assertOk()->assertJsonPath('data.0.cost_price', '75000.00');
    }

    public function test_phone_requires_customers_contact_scope(): void
    {
        Customer::factory()->create(['name' => 'Pelanggan Uji', 'phone' => '081234567890']);
        $service = app(ApiTokenService::class);

        $readOnly = $service->issue($this->owner, 'Baca Saja', ['customers.read']);
        $response = $this->withHeaders($this->bearer($readOnly['plain']))->getJson('/api/v1/customers');
        $response->assertOk()
            ->assertJsonPath('data.0.name', 'Pelanggan Uji')
            ->assertJsonMissingPath('data.0.phone');

        $withContact = $service->issue($this->owner, 'Dengan Kontak', ['customers.read', 'customers.contact']);
        $response = $this->withHeaders($this->bearer($withContact['plain']))->getJson('/api/v1/customers');
        $response->assertOk()->assertJsonPath('data.0.phone', '081234567890');
    }

    public function test_expired_token_is_rejected_and_unexpired_forms_work(): void
    {
        Product::factory()->create(['sku' => 'EXP-1']);
        $service = app(ApiTokenService::class);

        // Tanpa kedaluwarsa: tetap sah (kompatibilitas token lama).
        $forever = $service->issue($this->owner, 'Tanpa Batas', ['products.read']);
        $this->withHeaders($this->bearer($forever['plain']))->getJson('/api/v1/products')->assertOk();

        // Kedaluwarsa di masa depan: sah.
        $future = $service->issue($this->owner, 'Sebulan', ['products.read'], now()->addMonth());
        $this->withHeaders($this->bearer($future['plain']))->getJson('/api/v1/products')->assertOk();

        // Lewat masa berlaku: ditolak seperti token tercabut.
        $future['token']->forceFill(['expires_at' => now()->subMinute()])->save();
        $this->assertFalse($future['token']->refresh()->isUsable());
        $this->withHeaders($this->bearer($future['plain']))->getJson('/api/v1/products')->assertUnauthorized();
    }

    public function test_ui_issue_accepts_expiry_and_lists_it(): void
    {
        $expiry = now()->addDays(30);
        $response = $this->actingAs($this->owner)->post(route('api-tokens.store'), [
            'name' => 'Token Berjangka',
            'scopes' => ['products.read', 'products.cost'],
            'expires_at' => $expiry->format('Y-m-d\TH:i'),
        ]);
        $response->assertRedirect(route('api-tokens.index'));
        $token = ApiToken::where('name', 'Token Berjangka')->firstOrFail();
        $this->assertNotNull($token->expires_at);
        $this->assertTrue($token->expires_at->equalTo($expiry->startOfMinute()));

        // Masa lalu ditolak validasi.
        $this->actingAs($this->owner)->post(route('api-tokens.store'), [
            'name' => 'Token Lampau', 'scopes' => ['products.read'],
            'expires_at' => now()->subDay()->format('Y-m-d\TH:i'),
        ])->assertSessionHasErrors('expires_at');
        $this->assertSame(0, ApiToken::where('name', 'Token Lampau')->count());
    }
}
