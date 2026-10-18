<?php

namespace Tests\Feature\Lab;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Lab\Models\QcPointTemplate;
use App\Modules\Lab\Services\QcTemplateService;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\QcPointTemplateSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F2.1 — Template titik QC: seeder 13 titik bawaan + UI kelola
 * (wajib/tidak, urutan, aktif) yang hanya boleh Owner/Admin.
 */
class QcTemplateTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $technician;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IdentitySeeder::class);

        $this->owner = User::where('username', 'owner')->firstOrFail();
        $this->technician = User::create([
            'name' => 'Teknisi Uji', 'username' => 'teknisiuji', 'password' => 'password', 'is_active' => true,
        ]);
        $this->technician->roles()->attach(Role::where('name', 'Teknisi')->firstOrFail());
    }

    public function test_seeder_plants_thirteen_required_active_points_in_order(): void
    {
        $this->seed(QcPointTemplateSeeder::class);

        $points = QcPointTemplate::activeOrdered()->get();
        $this->assertCount(13, $points);
        $this->assertTrue($points->every(fn (QcPointTemplate $p) => $p->is_required && $p->is_active));
        $this->assertSame(range(1, 13), $points->pluck('sort_order')->all());

        // Penanaman ulang tidak menggandakan dan tidak menimpa.
        $this->seed(QcPointTemplateSeeder::class);
        $this->assertSame(13, QcPointTemplate::count());
    }

    public function test_owner_manages_points_via_http(): void
    {
        $this->seed(QcPointTemplateSeeder::class);

        $this->actingAs($this->owner)->get(route('qc-templates.index'))->assertOk()->assertSee('Template Titik QC');

        $this->actingAs($this->owner)->post(route('qc-templates.store'), [
            'name' => 'Uji titik tambahan', 'is_required' => '1', 'sort_order' => 20,
        ])->assertRedirect(route('qc-templates.index'));
        $extra = QcPointTemplate::where('name', 'Uji titik tambahan')->firstOrFail();
        $this->assertTrue($extra->is_required);
        $this->assertSame(20, $extra->sort_order);

        $this->actingAs($this->owner)->put(route('qc-templates.update', $extra), [
            'name' => 'Uji titik sunting', 'sort_order' => 5,
        ])->assertRedirect(route('qc-templates.index'));
        $extra->refresh();
        $this->assertSame('Uji titik sunting', $extra->name);
        $this->assertFalse($extra->is_required);
        $this->assertSame(5, $extra->sort_order);

        $this->actingAs($this->owner)->post(route('qc-templates.toggle', $extra))->assertRedirect(route('qc-templates.index'));
        $this->assertFalse($extra->refresh()->is_active);
    }

    public function test_admin_can_manage_but_technician_is_denied(): void
    {
        $service = app(QcTemplateService::class);

        $admin = User::create(['name' => 'Admin Uji', 'username' => 'adminuji', 'password' => 'password', 'is_active' => true]);
        $admin->roles()->attach(Role::where('name', 'Admin')->firstOrFail());
        $made = $service->create($admin, 'Titik admin', true);
        $this->assertSame('Titik admin', $made->name);

        // Teknisi (memegang lab.qc/lab.assign) BUKAN pengelola template.
        try {
            $service->create($this->technician, 'Titik liar', true);
            $this->fail('Teknisi seharusnya ditolak mengelola template QC.');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }

        $this->actingAs($this->technician)
            ->post(route('qc-templates.store'), ['name' => 'Titik liar'])
            ->assertForbidden();
        $this->actingAs($this->technician)->get(route('qc-templates.create'))->assertForbidden();
        // Melihat daftar tetap boleh (izin lab.view).
        $this->actingAs($this->technician)->get(route('qc-templates.index'))->assertOk();
    }

    public function test_actor_without_lab_view_is_denied(): void
    {
        // Gudang tidak memegang lab.* sama sekali (matriks peran Lab & QC).
        $gudang = User::create(['name' => 'Gudang Uji', 'username' => 'gudanguji', 'password' => 'password', 'is_active' => true]);
        $gudang->roles()->attach(Role::where('name', 'Gudang')->firstOrFail());

        $this->actingAs($gudang)->get(route('qc-templates.index'))->assertForbidden();

        // Kasir memegang lab.view (lihat status): boleh MELIHAT daftar,
        // tetapi mengelola tetap tertolak (bukan Owner/Admin).
        $kasir = User::create(['name' => 'Kasir Uji', 'username' => 'kasiruji', 'password' => 'password', 'is_active' => true]);
        $kasir->roles()->attach(Role::where('name', 'Kasir')->firstOrFail());

        $this->actingAs($kasir)->get(route('qc-templates.index'))->assertOk();
        $this->actingAs($kasir)->post(route('qc-templates.store'), ['name' => 'Titik liar'])->assertForbidden();
    }
}
