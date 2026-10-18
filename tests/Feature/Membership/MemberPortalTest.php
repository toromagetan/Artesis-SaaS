<?php

namespace Tests\Feature\Membership;

use App\Modules\Catalog\Models\Product;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Master\Models\Branch;
use App\Modules\Membership\Models\MemberPortalToken;
use App\Modules\Membership\Services\MemberPortalService;
use App\Modules\Membership\Services\PointsService;
use App\Modules\Sales\Services\SalesService;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipLevelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F3.7 Portal member: token hash-only, sekali-aktif per pelanggan,
 * dapat dicabut; halaman publik hanya memuat data pemilik token.
 */
class MemberPortalTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Branch $branch;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, AccountingSeeder::class, MembershipLevelSeeder::class]);
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $this->branch = Branch::where('name', 'Pusat')->firstOrFail();
        $this->customer = Customer::factory()->create([
            'branch_id' => $this->branch->id, 'name' => 'Portal Punya Sinta',
        ]);
    }

    public function test_portal_shows_only_the_token_owners_data(): void
    {
        $portal = app(MemberPortalService::class);
        $points = app(PointsService::class);
        $points->adjust($this->owner, $this->customer, 77, 'modal portal');

        $frame = Product::factory()->ofType('frame')->create(['sale_price' => '250000']);
        app(InventoryService::class)
            ->recordMovement($this->owner, $frame, $this->branch, 'initial_stock', 5, 'test_seed', 1);
        $ownSale = app(SalesService::class)->createSale($this->owner, [
            'customer_id' => $this->customer->id, 'sale_date' => '2026-10-18', 'tax_mode' => 'inclusive',
        ], [['product_id' => $frame->id, 'qty' => 1]], [['method' => 'cash', 'amount' => '250000']]);

        // Pelanggan lain dengan penjualannya sendiri.
        $other = Customer::factory()->create(['branch_id' => $this->branch->id, 'name' => 'Orang Lain']);
        $otherSale = app(SalesService::class)->createSale($this->owner, [
            'customer_id' => $other->id, 'sale_date' => '2026-10-18', 'tax_mode' => 'inclusive',
        ], [['product_id' => $frame->id, 'qty' => 1]], [['method' => 'cash', 'amount' => '250000']]);

        $token = $portal->issueToken($this->customer);
        $response = $this->get('/m/'.$token);
        $response->assertOk();
        $response->assertSee('Portal Punya Sinta');
        $response->assertSee($ownSale->number);
        $response->assertDontSee($otherSale->number);
        $response->assertDontSee('Orang Lain');
        // Saldo poin tampil (77 manual + earn penjualan sendiri).
        $response->assertSee(number_format($points->balance($this->customer), 0, ',', '.'));
    }

    public function test_only_the_hash_is_stored_and_tokens_rotate(): void
    {
        $portal = app(MemberPortalService::class);
        $first = $portal->issueToken($this->customer);

        $row = MemberPortalToken::firstOrFail();
        $this->assertSame(hash('sha256', $first), $row->token_hash);
        $this->assertStringNotContainsString($first, (string) json_encode($row->getAttributes()));

        // Terbitkan ulang -> token lama mati, token baru hidup.
        $second = $portal->issueToken($this->customer);
        $this->get('/m/'.$first)->assertNotFound();
        $this->get('/m/'.$second)->assertOk();
    }

    public function test_revoked_and_unknown_tokens_get_404(): void
    {
        $portal = app(MemberPortalService::class);
        $token = $portal->issueToken($this->customer);
        $this->get('/m/'.$token)->assertOk();

        $portal->revoke($this->customer);
        $this->get('/m/'.$token)->assertNotFound();
        $this->get('/m/token-ngawur-apa-saja')->assertNotFound();
    }

    public function test_staff_issue_and_revoke_endpoints_are_permission_gated(): void
    {
        // Owner menerbitkan via HTTP: flash memuat URL portal sekali lihat.
        $response = $this->actingAs($this->owner)->post('/member/'.$this->customer->id.'/portal');
        $response->assertRedirect(route('members.index'));
        $url = session('portal_url');
        $this->assertNotNull($url);
        $token = basename((string) parse_url((string) $url, PHP_URL_PATH));
        $this->get('/m/'.$token)->assertOk();

        // Cabut via HTTP.
        $this->actingAs($this->owner)->post('/member/'.$this->customer->id.'/portal/cabut')->assertRedirect();
        $this->get('/m/'.$token)->assertNotFound();

        // Kasir (tanpa members.manage) ditolak.
        $kasir = User::factory()->create();
        $kasir->roles()->sync([Role::where('name', 'Kasir')->firstOrFail()->id]);
        $this->actingAs($kasir)->post('/member/'.$this->customer->id.'/portal')->assertForbidden();
    }
}
