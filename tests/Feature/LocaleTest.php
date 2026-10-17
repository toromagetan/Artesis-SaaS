<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Multi-bahasa id/en (ADR-011): bawaan id, pengalih menyimpan preferensi
 * per pengguna, tamu memakai sesi, locale liar ditolak.
 */
class LocaleTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, AccountingSeeder::class]);
        $this->owner = User::where('username', 'owner')->firstOrFail();
    }

    public function test_default_locale_is_indonesian(): void
    {
        $this->actingAs($this->owner)
            ->get('/pengguna')
            ->assertOk()
            ->assertSee('Pengguna')
            ->assertSee('Buat Pengguna');
    }

    public function test_switching_to_english_persists_on_user_and_renders_english(): void
    {
        $this->actingAs($this->owner)
            ->post('/bahasa/en')
            ->assertRedirect();

        $this->assertSame('en', $this->owner->refresh()->locale);

        $this->actingAs($this->owner)
            ->get('/pengguna')
            ->assertOk()
            ->assertSee('Create User');

        $this->actingAs($this->owner)
            ->get('/pengguna/buat')
            ->assertOk()
            ->assertSee('Managed Branches');
    }

    public function test_switching_back_to_indonesian(): void
    {
        $this->owner->update(['locale' => 'en']);

        $this->actingAs($this->owner)->post('/bahasa/id');
        $this->assertSame('id', $this->owner->refresh()->locale);

        $this->actingAs($this->owner)
            ->get('/cabang')
            ->assertOk()
            ->assertSee('Buat Cabang');
    }

    public function test_guest_switch_uses_session_on_login_page(): void
    {
        $this->post('/bahasa/en');

        $this->get('/login')
            ->assertOk()
            ->assertSee('Username or Email');
    }

    public function test_unsupported_locale_is_rejected(): void
    {
        $this->actingAs($this->owner)
            ->post('/bahasa/fr')
            ->assertNotFound();
    }
}
