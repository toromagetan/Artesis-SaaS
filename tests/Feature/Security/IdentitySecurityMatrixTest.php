<?php

namespace Tests\Feature\Security;

use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\RoleService;
use App\Modules\Master\Models\Branch;
use App\Support\Concerns\BranchScoped;
use Database\Seeders\IdentitySeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Matriks keamanan 6 kasus (Blueprint G.1.2) untuk modul Identity —
 * kasus tetap yang WAJIB lulus di setiap modul:
 * (a) berizin lolos · (b) tanpa izin 403 · (c) IDOR antar cabang
 * baca+tulis ditolak · (d) is_platform ditolak di aksi bisnis ·
 * (e) user nonaktif ditolak di semua rute · (f) izin dicabut →
 * request berikutnya ditolak.
 */
class IdentitySecurityMatrixTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Branch $branchA;

    private Branch $branchB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IdentitySeeder::class);
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $this->branchA = Branch::where('name', 'Pusat')->firstOrFail();
        $this->branchB = Branch::create(['name' => 'Cabang B']);
    }

    /** Pengguna dengan peran khusus pemegang users.manage + branches.manage, terikat cabang A saja. */
    private function branchManager(): User
    {
        $role = Role::create(['name' => 'pengelola', 'label' => 'Pengelola']);
        $role->permissions()->sync(
            Permission::whereIn('name', ['dashboard.view', 'users.manage', 'branches.manage'])->pluck('id')
        );

        $user = User::factory()->create();
        $user->roles()->sync([$role->id]);
        $user->branches()->sync([$this->branchA->id]);

        return $user;
    }

    private function kasir(): User
    {
        $user = User::factory()->create();
        $user->roles()->sync([Role::where('name', 'kasir')->firstOrFail()->id]);

        return $user;
    }

    // (a) Aktor berizin lolos baca & tulis.
    public function test_a_permitted_actor_passes_read_and_write(): void
    {
        $this->actingAs($this->owner)->get('/pengguna')->assertOk();

        $this->actingAs($this->owner)->post('/pengguna', [
            'name' => 'Staf Baru',
            'username' => 'stafbaru',
            'password' => 'password-kuat',
            'roles' => ['kasir'],
            'branches' => [$this->branchA->id],
        ])->assertRedirect('/pengguna');

        $this->assertDatabaseHas('users', ['username' => 'stafbaru']);
    }

    // (b) Aktor tanpa izin ditolak 403 di baca & tulis.
    public function test_b_actor_without_permission_gets_403(): void
    {
        $kasir = $this->kasir();

        $this->actingAs($kasir)->get('/pengguna')->assertForbidden();
        $this->actingAs($kasir)->get('/peran')->assertForbidden();
        $this->actingAs($kasir)->get('/cabang')->assertForbidden();
        $this->actingAs($kasir)->post('/pengguna', [
            'name' => 'X', 'username' => 'x1', 'password' => 'password-kuat',
        ])->assertForbidden();
    }

    // (c) IDOR antar cabang: baca (pencarian global) dan tulis (scope) ditolak.
    public function test_c_cross_branch_idor_read_and_write_denied(): void
    {
        $manager = $this->branchManager();

        $userB = User::factory()->create(['name' => 'Orang Cabang B']);
        $userB->branches()->sync([$this->branchB->id]);

        // Baca: pencarian global tidak membocorkan pengguna/ cabang tetangga.
        $html = $this->actingAs($manager)->get('/cari?q=Cabang')->assertOk()->getContent();
        $this->assertStringNotContainsString('Orang Cabang B', $html);
        $this->assertStringNotContainsString('Cabang B', $html);

        // Tulis: model BranchScoped — baris cabang B tidak terlihat & tidak ter-update.
        Schema::create('matrix_probes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id');
            $table->string('note')->nullable();
            $table->timestamps();
        });
        $probeClass = new class extends Model
        {
            use BranchScoped;

            protected $table = 'matrix_probes';

            protected $guarded = [];
        };
        $rowB = $probeClass::class;
        $probe = new $probeClass;
        // Baris cabang B ditanam tanpa scope (lewat koneksi mentah).
        DB::table('matrix_probes')->insert([
            'branch_id' => $this->branchB->id, 'note' => 'milik B', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('matrix_probes')->insert([
            'branch_id' => $this->branchA->id, 'note' => 'milik A', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($manager);
        $visible = $rowB::query()->pluck('note')->all();
        $this->assertSame(['milik A'], $visible);

        $affected = $rowB::query()->where('note', 'milik B')->update(['note' => 'dibajak']);
        $this->assertSame(0, $affected);
        $this->assertSame('milik B', DB::table('matrix_probes')->where('branch_id', $this->branchB->id)->value('note'));
    }

    // (c2) Kontrak master data (dikunci sadar, hasil audit gerbang Fase 0):
    // pengguna & cabang adalah data master level tenant — pagarnya IZIN
    // (kasus b), bukan cabang; daftar dan detail konsisten sama-sama
    // terbuka bagi pemegang izin. Pagar cabang berlaku pada data bisnis
    // (kasus c: BranchScope + pencarian global), bukan pada master data.
    public function test_c2_master_data_is_permission_fenced_not_branch_fenced(): void
    {
        $manager = $this->branchManager();
        $userB = User::factory()->create();
        $userB->branches()->sync([$this->branchB->id]);

        $this->actingAs($manager)->get('/cabang/'.$this->branchB->id)->assertOk();
        $this->actingAs($manager)->get('/pengguna/'.$userB->id)->assertOk();

        // Tanpa izin tetap tertolak di URL yang sama.
        $kasir = $this->kasir();
        $this->actingAs($kasir)->get('/cabang/'.$this->branchB->id)->assertForbidden();
        $this->actingAs($kasir)->get('/pengguna/'.$userB->id)->assertForbidden();
    }

    // (d) Aktor is_platform ditolak pada aksi bisnis.
    public function test_d_platform_actor_denied_on_business_actions(): void
    {
        $platform = User::where('username', 'platform')->firstOrFail();
        $this->assertTrue($platform->is_platform);

        $this->actingAs($platform)->get('/pengguna')->assertForbidden();
        $this->actingAs($platform)->get('/cabang')->assertForbidden();
        $this->actingAs($platform)->post('/pengguna', [
            'name' => 'X', 'username' => 'x2', 'password' => 'password-kuat',
        ])->assertForbidden();
    }

    // (e) Pengguna nonaktif ditolak di semua rute (sesi berjalan pun diputus).
    public function test_e_inactive_user_denied_on_all_routes(): void
    {
        $user = $this->kasir();
        $user->roles()->sync([Role::where('name', 'admin')->firstOrFail()->id]);

        // Nonaktifkan di DB, lalu segarkan aktor seperti request berikutnya.
        User::where('id', $user->id)->update(['is_active' => false]);
        $fresh = User::findOrFail($user->id);

        $this->actingAs($fresh)->get('/dashboard')->assertRedirect('/login');
        $this->actingAs($fresh)->get('/pengguna')->assertRedirect('/login');
        $this->actingAs($fresh)->get('/profile')->assertRedirect('/login');
    }

    // (f) Izin dicabut → request berikutnya ditolak.
    public function test_f_revoked_permission_denies_next_request(): void
    {
        $kasir = $this->kasir();
        $kasirRole = Role::where('name', 'kasir')->firstOrFail();

        // Beri users.manage ke peran Kasir lewat jalur sah (RoleService).
        $granted = $kasirRole->permissions()->pluck('permissions.name')->all();
        app(RoleService::class)->syncPermissions($kasirRole, [...$granted, 'users.manage'], $this->owner);
        $this->actingAs($kasir)->get('/pengguna')->assertOk();

        // Cabut kembali → request berikutnya langsung 403.
        app(RoleService::class)->syncPermissions($kasirRole, $granted, $this->owner);
        $this->actingAs($kasir)->get('/pengguna')->assertForbidden();
    }
}
