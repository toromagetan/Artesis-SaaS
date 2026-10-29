<?php

namespace Tests\Feature\Platform;

use App\Modules\Identity\Models\User;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit 23 Okt 2026 (D1): halaman muka `/` adalah Artesis — bukan
 * halaman sambutan bawaan Laravel — dan pengguna yang sudah login
 * diarahkan ke dashboard.
 */
class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_sees_lentera_landing_not_laravel_welcome(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Artesis')
            ->assertSee('Jernih Datanya, Terang Bisnisnya')
            ->assertSee(route('login'))
            ->assertDontSee('Laravel');
    }

    public function test_guest_landing_renders_in_english(): void
    {
        $this->withSession(['locale' => 'en'])->get('/')
            ->assertOk()
            ->assertSee('Sign in')
            ->assertSee('Multi-Branch Stock');
    }

    public function test_authenticated_user_is_redirected_to_dashboard(): void
    {
        $this->seed(IdentitySeeder::class);
        $owner = User::where('username', 'owner')->firstOrFail();

        $this->actingAs($owner)->get('/')->assertRedirect(route('dashboard'));
    }
}
