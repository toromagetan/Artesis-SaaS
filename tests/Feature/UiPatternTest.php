<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pola UI F0.5 (Blueprint F.2 + skill lentera-frontend): Varian A, judul
 * tepat satu kali, GlobalSearch selalu di header, sensus dropdown
 * (1 Aksi + 1 Cetak per layar daftar), detail = halaman penuh,
 * mesin cetak A5, pencarian global terpagar izin + cabang.
 */
class UiPatternTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IdentitySeeder::class);
        $this->owner = User::where('username', 'owner')->firstOrFail();
    }

    public function test_global_search_is_present_in_header(): void
    {
        $html = $this->actingAs($this->owner)->get('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('name="q"', $html);
        $this->assertStringContainsString('/cari', $html);
    }

    public function test_title_appears_exactly_once_as_single_h1(): void
    {
        $html = $this->actingAs($this->owner)->get('/pengguna')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '<h1'));
    }

    public function test_users_list_button_census(): void
    {
        $html = $this->actingAs($this->owner)->get('/pengguna')->assertOk()->getContent();

        // Tepat satu DropdownAksi layar + satu DropdownCetak;
        // dropdown per baris = jumlah baris (2 pengguna terseed).
        $this->assertSame(1, substr_count($html, 'data-lentera="dropdown-aksi"'));
        $this->assertSame(1, substr_count($html, 'data-lentera="dropdown-cetak"'));
        $this->assertSame(2, substr_count($html, 'data-lentera="row-actions"'));
    }

    public function test_user_detail_is_a_full_page(): void
    {
        $this->actingAs($this->owner)
            ->get('/pengguna/'.$this->owner->id)
            ->assertOk()
            ->assertSee($this->owner->name)
            ->assertSee('Terakhir Masuk');
    }

    public function test_branch_detail_is_a_full_page(): void
    {
        $branchId = Branch::firstOrFail()->id;

        $this->actingAs($this->owner)
            ->get('/cabang/'.$branchId)
            ->assertOk()
            ->assertSee('Detail Cabang');
    }

    public function test_print_documents_render_on_a5_engine(): void
    {
        $list = $this->actingAs($this->owner)->get('/pengguna/cetak')->assertOk()->getContent();
        $this->assertStringContainsString('A5 portrait', $list);
        $this->assertStringContainsString('Lentera', $list); // nama toko dari settings
        $this->assertStringContainsString($this->owner->name, $list);

        $profile = $this->actingAs($this->owner)->get('/pengguna/'.$this->owner->id.'/cetak')->assertOk()->getContent();
        $this->assertStringContainsString('Profil Pengguna', $profile);
        $this->assertStringContainsString($this->owner->username, $profile);
    }

    public function test_global_search_finds_user_for_privileged_actor(): void
    {
        $this->actingAs($this->owner)
            ->get('/cari?q=owner')
            ->assertOk()
            ->assertSee('Hasil Pencarian')
            ->assertSee($this->owner->name);
    }

    public function test_global_search_is_permission_fenced(): void
    {
        $kasir = User::factory()->create(['username' => 'kasirx']);
        $kasir->roles()->sync([Role::where('name', 'kasir')->firstOrFail()->id]);

        // Kasir tidak memegang users.manage → kelompok pengguna tidak bocor.
        $this->actingAs($kasir)
            ->get('/cari?q=owner')
            ->assertOk()
            ->assertDontSee($this->owner->name)
            ->assertSee('Tidak ada hasil');
    }

    public function test_global_search_requires_login(): void
    {
        $this->get('/cari?q=owner')->assertRedirect('/login');
    }
}
