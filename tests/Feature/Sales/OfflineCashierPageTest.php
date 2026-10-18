<?php

namespace Tests\Feature\Sales;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F5.3b halaman klien kasir offline — terpagar sales.create,
 * memuat kontrak API perangkat, dan aset PWA tersedia.
 */
class OfflineCashierPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_page_is_permission_fenced_and_wired_to_device_api(): void
    {
        $this->seed(IdentitySeeder::class);
        $owner = User::where('username', 'owner')->firstOrFail();

        $this->actingAs($owner)->get(route('offline-cashier.show'))
            ->assertOk()
            ->assertSee('/api/v1/offline/snapshot', false)
            ->assertSee('/api/v1/offline/sync', false)
            ->assertSee('kasir-sw.js', false)
            ->assertSee('localStorage', false);

        $gudang = User::factory()->create();
        $gudang->roles()->sync([Role::where('name', 'Gudang')->firstOrFail()->id]);
        $this->actingAs($gudang)->get(route('offline-cashier.show'))->assertForbidden();

        $this->assertFileExists(public_path('kasir-sw.js'));
        $this->assertFileExists(public_path('kasir-manifest.json'));
    }
}
