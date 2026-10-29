<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Multi-bahasa id/en/ms/zh (ADR-011 + M2): bawaan id, pengalih menyimpan
 * preferensi per pengguna, tamu memakai sesi, locale liar ditolak.
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

    public function test_switching_to_malay_persists_and_renders_malay(): void
    {
        $this->actingAs($this->owner)
            ->post('/bahasa/ms')
            ->assertRedirect();

        $this->assertSame('ms', $this->owner->refresh()->locale);

        $this->actingAs($this->owner)
            ->get('/cabang')
            ->assertOk()
            ->assertSee('Buat Cawangan');
    }

    public function test_switching_to_chinese_persists_and_renders_chinese(): void
    {
        $this->actingAs($this->owner)
            ->post('/bahasa/zh')
            ->assertRedirect();

        $this->assertSame('zh', $this->owner->refresh()->locale);

        $this->actingAs($this->owner)
            ->get('/cabang')
            ->assertOk()
            ->assertSee('创建门店');
    }

    public function test_guest_switch_to_malay_and_chinese_on_landing_and_login(): void
    {
        $this->post('/bahasa/ms');

        $this->get('/')
            ->assertOk()
            ->assertSee('Jernih Datanya, Terang Bisnesnya');

        $this->post('/bahasa/zh');

        $this->get('/login')
            ->assertOk()
            ->assertSee('用户名或邮箱');
    }
}
