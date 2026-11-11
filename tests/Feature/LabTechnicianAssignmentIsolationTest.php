<?php

namespace Tests\Feature;

use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Lab\Models\LensProcess;
use App\Modules\Lab\Services\LensProcessService;
use App\Modules\Master\Models\Branch;
use App\Modules\Platform\Models\Plan;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\PlanService;
use App\Modules\Sales\Models\Sale;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\SaasSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Audit lanjutan temuan 7 — penugasan teknisi lab (LensProcessService)
 * harus gagal-tertutup per tenant pada tingkat SERVICE, bukan hanya
 * mengandalkan pagar controller/komisi: teknisi wajib seember dengan
 * penjualan sumber, dan aktor wajib pemilik ember proses yang sama.
 * Platform, unassigned, dan aktor tenant lain ditolak; jalur
 * standalone (ember NULL) dan HTTP tetap berfungsi.
 */
class LabTechnicianAssignmentIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $platform;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, AccountingSeeder::class, SaasSeeder::class]);
        $this->platform = User::where('username', 'platform')->firstOrFail();
    }

    /** @return array{Tenant, User, Branch} */
    private function tenantAdmin(string $tag): array
    {
        $tenant = Tenant::create([
            'name' => 'Optik '.$tag, 'slug' => 'optik-'.$tag, 'email' => $tag.'@uji.id',
        ]);
        app(PlanService::class)->assignPlan(
            $tenant,
            Plan::where('key', 'best_offer')->firstOrFail(),
            null,
            $this->platform,
        );
        $branch = Branch::create(['name' => 'Cabang '.$tag, 'tenant_id' => $tenant->id]);
        $admin = User::create([
            'name' => 'Admin '.$tag, 'username' => 'admin-'.$tag,
            'email' => 'admin-'.$tag.'@uji.id', 'password' => bcrypt('rahasia-uji'),
            'is_active' => true, 'is_platform' => false, 'tenant_id' => $tenant->id,
        ]);
        $admin->roles()->attach(Role::where('name', 'Owner')->firstOrFail());
        $admin->branches()->attach($branch);

        return [$tenant, $admin, $branch];
    }

    private function staffOf(Tenant $tenant, Branch $branch, string $username): User
    {
        $user = User::create([
            'name' => 'Staf '.$username, 'username' => $username,
            'email' => $username.'@uji.id', 'password' => bcrypt('rahasia-uji'),
            'is_active' => true, 'is_platform' => false, 'tenant_id' => $tenant->id,
        ]);
        $user->roles()->attach(Role::where('name', 'Kasir')->firstOrFail());
        $user->branches()->attach($branch);

        return $user;
    }

    private function processOf(User $cashier, Branch $branch, string $tag): LensProcess
    {
        $customer = Customer::create([
            'name' => 'Pelanggan '.$tag, 'phone' => '0844'.$tag,
            'branch_id' => $branch->id, 'tenant_id' => $branch->tenant_id,
        ]);
        $sale = Sale::create([
            'number' => 'SL-LAB-'.$tag, 'customer_id' => $customer->id,
            'branch_id' => $branch->id, 'cashier_id' => $cashier->id,
            'sale_date' => now()->toDateString(),
            'subtotal_amount' => 1000000, 'total_amount' => 1000000,
            'tax_amount' => 0, 'paid_amount' => 1000000, 'status' => 'active',
        ]);

        return LensProcess::create([
            'sale_id' => $sale->id, 'status' => 'waiting', 'started_at' => now(),
        ]);
    }

    public function test_same_tenant_assignment_succeeds_via_service(): void
    {
        [$tenantA, $adminA, $branchA] = $this->tenantAdmin('a');
        $technicianA = $this->staffOf($tenantA, $branchA, 'teknisi-a');
        $process = $this->processOf($adminA, $branchA, 'A1');

        $result = app(LensProcessService::class)
            ->assignTechnician($adminA, $process, $technicianA->id, 3);

        $this->assertSame($technicianA->id, (int) $result->technician_id);
        $this->assertSame(3, (int) $result->estimated_days);
    }

    public function test_cross_tenant_technician_rejected_via_service(): void
    {
        [$tenantA, $adminA, $branchA] = $this->tenantAdmin('a');
        [$tenantB, , $branchB] = $this->tenantAdmin('b');
        $technicianB = $this->staffOf($tenantB, $branchB, 'teknisi-b');
        $process = $this->processOf($adminA, $branchA, 'A2');

        try {
            app(LensProcessService::class)->assignTechnician($adminA, $process, $technicianB->id);
            $this->fail('Teknisi tenant lain seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('technician_id', $e->errors());
        }
        $this->assertNull($process->fresh()->technician_id);
    }

    public function test_other_tenant_actor_rejected_via_service(): void
    {
        [$tenantA, $adminA, $branchA] = $this->tenantAdmin('a');
        [$tenantB, $adminB, $branchB] = $this->tenantAdmin('b');
        $technicianB = $this->staffOf($tenantB, $branchB, 'teknisi-b');
        $process = $this->processOf($adminA, $branchA, 'A3');

        // Aktor B menugaskan teknisinya sendiri pada proses milik A:
        // kepemilikan proses terhadap aktor tidak cocok.
        $this->expectException(AuthorizationException::class);
        app(LensProcessService::class)->assignTechnician($adminB, $process, $technicianB->id);
    }

    public function test_platform_and_unassigned_actors_rejected_via_service(): void
    {
        [$tenantA, $adminA, $branchA] = $this->tenantAdmin('a');
        $technicianA = $this->staffOf($tenantA, $branchA, 'teknisi-a');
        $process = $this->processOf($adminA, $branchA, 'A4');
        $service = app(LensProcessService::class);

        try {
            $service->assignTechnician($this->platform, $process, $technicianA->id);
            $this->fail('Aktor platform seharusnya ditolak.');
        } catch (AuthorizationException) {
            $this->assertNull($process->fresh()->technician_id);
        }

        $unassigned = User::create([
            'name' => 'Yatim Lab', 'username' => 'yatim-lab',
            'email' => 'yatim-lab@uji.id', 'password' => bcrypt('rahasia-uji'),
            'is_active' => true, 'is_platform' => false, 'tenant_id' => null,
        ]);
        $unassigned->roles()->attach(Role::where('name', 'Owner')->firstOrFail());
        try {
            $service->assignTechnician($unassigned, $process, $technicianA->id);
            $this->fail('Aktor unassigned seharusnya ditolak.');
        } catch (AuthorizationException) {
            $this->assertNull($process->fresh()->technician_id);
        }
    }

    public function test_standalone_assignment_still_works(): void
    {
        // Tanpa tenant sama sekali: instalasi mandiri, ember NULL.
        $branch = Branch::create(['name' => 'Cabang Mandiri', 'tenant_id' => null]);
        $owner = User::create([
            'name' => 'Pemilik Mandiri', 'username' => 'pemilik-mandiri',
            'email' => 'mandiri@uji.id', 'password' => bcrypt('rahasia-uji'),
            'is_active' => true, 'is_platform' => false, 'tenant_id' => null,
        ]);
        $owner->roles()->attach(Role::where('name', 'Owner')->firstOrFail());
        $owner->branches()->attach($branch);
        $technician = User::create([
            'name' => 'Teknisi Mandiri', 'username' => 'teknisi-mandiri',
            'email' => 'teknisi-mandiri@uji.id', 'password' => bcrypt('rahasia-uji'),
            'is_active' => true, 'is_platform' => false, 'tenant_id' => null,
        ]);
        $technician->roles()->attach(Role::where('name', 'Kasir')->firstOrFail());
        $technician->branches()->attach($branch);

        $process = $this->processOf($owner, $branch, 'M1');
        $result = app(LensProcessService::class)
            ->assignTechnician($owner, $process, $technician->id);

        $this->assertSame($technician->id, (int) $result->technician_id);
    }

    public function test_http_paths_do_not_cross_tenants(): void
    {
        [$tenantA, $adminA, $branchA] = $this->tenantAdmin('a');
        [$tenantB, $adminB, $branchB] = $this->tenantAdmin('b');
        $technicianB = $this->staffOf($tenantB, $branchB, 'teknisi-b');
        $process = $this->processOf($adminA, $branchA, 'A5');

        // Aktor B tidak dapat membaca proses A lewat HTTP: binding
        // rute terpagar cabang menjawab 404 (keberadaan tidak bocor).
        $this->actingAs($adminB)
            ->get(route('lab.show', $process))
            ->assertNotFound();

        // Aktor B tidak dapat menugaskan lewat HTTP.
        $this->actingAs($adminB)
            ->post(route('lab.assign', $process), ['technician_id' => $technicianB->id])
            ->assertNotFound();
        $this->assertNull($process->fresh()->technician_id);

        // Aktor A menugaskan teknisi B lewat HTTP: lolos pagar
        // controller (proses miliknya) tetapi ditolak service —
        // kembali dengan galat validasi, tanpa perubahan data.
        $this->actingAs($adminA)
            ->post(route('lab.assign', $process), ['technician_id' => $technicianB->id])
            ->assertSessionHasErrors('technician_id');
        $this->assertNull($process->fresh()->technician_id);
    }
}
