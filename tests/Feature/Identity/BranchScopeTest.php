<?php

namespace Tests\Feature\Identity;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use App\Support\Concerns\BranchScoped;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Model uji khusus untuk membuktikan perilaku BranchScoped/BranchScope. */
class BranchScopeProbe extends Model
{
    use BranchScoped;

    protected $table = 'branch_probes';

    public $timestamps = false;

    protected $guarded = [];
}

/**
 * Pagar cabang di level query (ADR-007): model BranchScoped hanya
 * menampilkan baris cabang terkelola aktor; Owner melihat semua;
 * penulisan baru terisi cabang konteks secara otomatis.
 */
class BranchScopeTest extends TestCase
{
    use RefreshDatabase;

    private Branch $pusat;

    private Branch $timur;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, AccountingSeeder::class]);
        $this->pusat = Branch::where('name', 'Pusat')->firstOrFail();
        $this->timur = Branch::create(['name' => 'Timur']);

        Schema::create('branch_probes', function ($table) {
            $table->id();
            $table->foreignId('branch_id')->nullable();
            $table->string('name');
        });

        DB::table('branch_probes')->insert([
            ['branch_id' => $this->pusat->id, 'name' => 'probe-pusat'],
            ['branch_id' => $this->timur->id, 'name' => 'probe-timur'],
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('branch_probes');
        parent::tearDown();
    }

    private function kasirPusat(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', 'Kasir')->firstOrFail());
        $user->branches()->attach($this->pusat->id);

        return $user;
    }

    public function test_staff_sees_only_own_branch_rows(): void
    {
        $this->actingAs($this->kasirPusat());

        $names = BranchScopeProbe::pluck('name')->all();
        $this->assertSame(['probe-pusat'], $names);
    }

    public function test_owner_sees_all_branches_rows(): void
    {
        $this->actingAs(User::where('username', 'owner')->firstOrFail());

        $this->assertSame(2, BranchScopeProbe::count());
    }

    public function test_user_without_branches_sees_nothing(): void
    {
        $this->actingAs(User::factory()->create());

        $this->assertSame(0, BranchScopeProbe::count());
    }

    public function test_new_record_gets_context_branch_automatically(): void
    {
        $this->actingAs($this->kasirPusat());

        $probe = BranchScopeProbe::create(['name' => 'probe-baru']);
        $this->assertSame($this->pusat->id, $probe->branch_id);
    }

    public function test_direct_lookup_of_foreign_row_returns_null(): void
    {
        $this->actingAs($this->kasirPusat());

        $foreign = DB::table('branch_probes')->where('name', 'probe-timur')->first();
        $this->assertNull(BranchScopeProbe::find($foreign->id));
    }
}
