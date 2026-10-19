<?php

namespace Tests\Feature\Platform;

use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use App\Modules\Platform\Models\AuditLog;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Penampil Log Aktivitas: gerbang settings.manage, pagar cabang
 * pada daftar & detail, filter aksi/subjek.
 */
class AuditLogViewerTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Branch $branch;

    private Branch $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IdentitySeeder::class);
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $this->branch = Branch::where('name', 'Pusat')->firstOrFail();
        $this->other = Branch::create(['name' => 'Cabang Lain', 'is_active' => true]);

        AuditLog::create([
            'user_id' => $this->owner->id, 'action' => 'sale.create', 'subject_type' => 'Sale',
            'subject_id' => 11, 'branch_id' => $this->branch->id, 'new_values' => ['total' => '150000'],
        ]);
        AuditLog::create([
            'user_id' => $this->owner->id, 'action' => 'po.approve', 'subject_type' => 'PurchaseOrder',
            'subject_id' => 7, 'branch_id' => $this->other->id, 'new_values' => ['status' => 'sent'],
        ]);
        AuditLog::create([
            'user_id' => null, 'action' => 'platform.boot', 'subject_type' => 'System',
            'subject_id' => 0, 'branch_id' => null, 'new_values' => [],
        ]);
    }

    public function test_owner_sees_all_logs_filters_and_detail(): void
    {
        $this->actingAs($this->owner)->get(route('audit-logs.index'))
            ->assertOk()
            ->assertSee('sale.create')
            ->assertSee('po.approve')
            ->assertSee('platform.boot');

        $this->actingAs($this->owner)->get(route('audit-logs.index', ['aksi' => 'sale']))
            ->assertOk()
            ->assertSee('sale.create')
            ->assertDontSee('po.approve');

        $log = AuditLog::where('action', 'sale.create')->firstOrFail();
        $this->actingAs($this->owner)->get(route('audit-logs.show', $log))
            ->assertOk()
            ->assertSee('sale.create')
            ->assertSee('150000');
    }

    public function test_permission_gate_and_branch_fence(): void
    {
        // Kasir tanpa settings.manage → 403.
        $kasir = User::factory()->create();
        $kasir->roles()->sync([Role::where('name', 'Kasir')->firstOrFail()->id]);
        $kasir->branches()->sync([$this->branch->id]);
        $this->actingAs($kasir)->get(route('audit-logs.index'))->assertForbidden();

        // Peran kustom settings.manage terpagar cabang Pusat: hanya log Pusat.
        $role = Role::create(['name' => 'PengaturanCabang', 'label' => 'Pengaturan Cabang']);
        $role->permissions()->sync(Permission::whereIn('name', ['dashboard.view', 'settings.manage'])->pluck('id'));
        $admin = User::factory()->create();
        $admin->roles()->sync([$role->id]);
        $admin->branches()->sync([$this->branch->id]);
        $this->actingAs($admin)->get(route('audit-logs.index'))
            ->assertOk()
            ->assertSee('sale.create')
            ->assertDontSee('po.approve')
            ->assertDontSee('platform.boot');

        $other = AuditLog::where('action', 'po.approve')->firstOrFail();
        $this->actingAs($admin)->get(route('audit-logs.show', $other))->assertForbidden();
    }
}
