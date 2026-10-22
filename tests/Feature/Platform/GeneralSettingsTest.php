<?php

namespace Tests\Feature\Platform;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Setting;
use App\Modules\Platform\Models\AuditLog;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit 23 Okt 2026 (B2): Pengaturan Umum & Pajak kini terkelola
 * dari UI (settings.manage) untuk kunci yang benar-benar dibaca
 * kode, dan identitas toko mengalir ke kepala dokumen cetak.
 */
class GeneralSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $kasir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IdentitySeeder::class);
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $branch = Branch::where('name', 'Pusat')->firstOrFail();
        $this->kasir = User::factory()->create();
        $this->kasir->roles()->sync([Role::where('name', 'Kasir')->firstOrFail()->id]);
        $this->kasir->branches()->sync([$branch->id]);
    }

    public function test_general_settings_are_permission_gated_and_persist(): void
    {
        $this->actingAs($this->kasir)->get(route('settings.general.edit'))->assertForbidden();

        $this->actingAs($this->owner)->get(route('settings.general.edit'))->assertOk();
        $this->actingAs($this->owner)->put(route('settings.general.update'), [
            'store_name' => 'Optik Istori',
            'store_address' => 'Jl. Raya Menganti No. 88, Surabaya',
            'store_phone' => '031-5551234',
            'tax_rate_default' => '11',
            'tax_mode' => 'inclusive',
            'discount_threshold' => '25',
            'technician_commission_rate' => '5',
        ])->assertRedirect(route('settings.general.edit'));

        $this->assertSame('Optik Istori', Setting::value('store.name'));
        $this->assertSame('Jl. Raya Menganti No. 88, Surabaya', Setting::value('store.address'));
        $this->assertSame('11', Setting::value('tax.rate_default'));
        $this->assertSame('inclusive', Setting::value('tax.mode'));
        $this->assertSame('25', Setting::value('sales.discount_threshold_percent'));
        $this->assertSame('5', Setting::value('lab.technician_commission_rate'));
        $this->assertTrue(AuditLog::where('action', 'settings.general.update')->exists());
    }

    public function test_store_identity_flows_into_print_header(): void
    {
        Setting::set('store.name', 'Optik Istori');
        Setting::set('store.address', 'Jl. Raya Menganti No. 88, Surabaya');
        Setting::set('store.phone', '031-5551234');

        // Cetak daftar pengguna memakai mesin cetak yang sama (tanpa
        // pemanggil yang memberi nama toko secara eksplisit).
        $this->actingAs($this->owner)->get(route('users.print'))
            ->assertOk()
            ->assertSee('Optik Istori')
            ->assertSee('Jl. Raya Menganti No. 88, Surabaya')
            ->assertSee('031-5551234');
    }
}
