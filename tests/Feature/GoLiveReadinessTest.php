<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use App\Modules\Platform\Models\Plan;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * M6 — Kesiapan go-live: penanaman penuh (DatabaseSeeder) harus
 * menghasilkan instalasi SaaS yang utuh — paket tertanam, aktor
 * platform dapat membuka panel — dan halaman publik kunci hidup.
 * Ini gerbang asap yang sama dengan daftar periksa smoke test di
 * docs/DEPLOY-RUNBOOK.md.
 */
class GoLiveReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_seed_produces_working_saas_installation(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(4, Plan::count());
        $this->assertNotNull(Plan::where('key', 'free')->first());
        $this->assertNotNull(Plan::where('key', 'best_offer')->first());

        $platform = User::where('username', 'platform')->firstOrFail();
        $this->assertTrue($platform->is_platform);
        $this->actingAs($platform)->get('/platform/paket')->assertOk();
        $this->actingAs($platform)->get('/platform/penagihan')->assertOk();
    }

    public function test_public_pages_respond(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->get('/')->assertOk();
        $this->get('/login')->assertOk();
        $this->get('/daftar')->assertOk();
    }
}
