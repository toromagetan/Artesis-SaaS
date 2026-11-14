<?php

namespace Tests\Feature\Comms;

use App\Modules\Catalog\Models\FrameDetail;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Master\Models\Branch;
use App\Modules\Platform\Models\Plan;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\PlanService;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Services\SalesService;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\SaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Skema WhatsApp wa.me (keputusan owner 15 Nov 2026): permukaan WA
 * API (inbox, kampanye, log, template, gateway) dinonaktifkan untuk
 * aktor tenant lewat middleware wa.api + sidebar; aktor instalasi
 * mandiri mempertahankannya. Tenant memakai tombol Kirim WA (wa.me)
 * di halaman transaksi — tersedia tanpa gateway dan tanpa paket WA.
 */
class WaSchemeChangeTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{Tenant, User, Branch} */
    private function tenantAdmin(string $tag): array
    {
        $platform = User::where('username', 'platform')->firstOrFail();
        $tenant = Tenant::create([
            'name' => 'Optik '.$tag, 'slug' => 'optik-'.$tag, 'email' => $tag.'@uji.id',
        ]);
        app(PlanService::class)->assignPlan(
            $tenant,
            Plan::where('key', 'best_offer')->firstOrFail(),
            null,
            $platform,
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

    public function test_tenant_actors_are_blocked_from_wa_api_surfaces(): void
    {
        $this->seed([IdentitySeeder::class, AccountingSeeder::class, SaasSeeder::class]);
        [, $adminA] = $this->tenantAdmin('a');

        // Owner tenant memegang semua izin wa.* & settings.manage —
        // yang menolak di sini murni gerbang wa.api.
        foreach (['wa-inbox.index', 'wa-campaigns.index', 'wa-log.index', 'wa-templates.index', 'wa-gateway.edit'] as $routeName) {
            $this->actingAs($adminA)->get(route($routeName))->assertForbidden();
        }
    }

    public function test_tenant_sidebar_hides_wa_api_menus(): void
    {
        $this->seed([IdentitySeeder::class, AccountingSeeder::class, SaasSeeder::class]);
        [, $adminA] = $this->tenantAdmin('a');

        $html = $this->actingAs($adminA)->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringNotContainsString(route('wa-inbox.index'), $html);
        $this->assertStringNotContainsString(route('wa-gateway.edit'), $html);
    }

    public function test_customer_page_offers_wa_me_button_for_tenant(): void
    {
        $this->seed([IdentitySeeder::class, AccountingSeeder::class, SaasSeeder::class]);
        [$tenant, $adminA, $branch] = $this->tenantAdmin('a');
        $customer = Customer::create([
            'name' => 'Pelanggan A', 'phone' => '0812-3456-7890',
            'branch_id' => $branch->id, 'tenant_id' => $tenant->id,
        ]);

        $this->actingAs($adminA)->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee('https://wa.me/6281234567890?text=')
            ->assertSee(__('ui.wa_link.send'));
    }

    public function test_standalone_owner_keeps_wa_api_access(): void
    {
        // Tanpa baris tenant sama sekali, owner = instalasi mandiri.
        $this->seed([IdentitySeeder::class, AccountingSeeder::class]);
        $owner = User::where('username', 'owner')->firstOrFail();

        $this->actingAs($owner)->get(route('wa-gateway.edit'))->assertOk();
        $this->actingAs($owner)->get(route('wa-inbox.index'))->assertOk();
    }

    public function test_sale_page_shows_wa_me_and_pdf_buttons(): void
    {
        $this->seed([IdentitySeeder::class, AccountingSeeder::class]);
        $owner = User::where('username', 'owner')->firstOrFail();
        $branch = Branch::where('name', 'Pusat')->firstOrFail();
        $customer = Customer::factory()->create(['branch_id' => $branch->id, 'phone' => '081234567890']);
        $frame = FrameDetail::factory()->create(['sale_price' => '400000']);
        app(InventoryService::class)->recordMovement($owner, $frame, $branch, 'initial_stock', 5, 'test_seed', 1);

        $sale = app(SalesService::class)->createSale($owner, [
            'customer_id' => $customer->id, 'sale_date' => '2026-10-18', 'tax_mode' => 'inclusive',
        ], [
            ['detail_type' => 'frame', 'detail_id' => $frame->id, 'qty' => 1],
        ], [
            ['method' => 'cash', 'amount' => '400000'],
        ]);
        $this->assertInstanceOf(Sale::class, $sale);

        $this->actingAs($owner)->get(route('sales.show', $sale))
            ->assertOk()
            ->assertSee('https://wa.me/6281234567890?text=')
            ->assertSee(route('sales.download-invoice', $sale))
            ->assertSee(__('ui.download_pdf'));
    }
}
