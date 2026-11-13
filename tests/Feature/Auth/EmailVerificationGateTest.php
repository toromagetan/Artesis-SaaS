<?php

namespace Tests\Feature\Auth;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use App\Modules\Platform\Models\Plan;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\PlanService;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\SaasSeeder;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * B-01 (keputusan kebijakan owner 2026-11-13): pengguna tenant
 * yang memiliki email tetapi belum memverifikasinya hanya dapat
 * membuka halaman verifikasi, meminta pengiriman ulang, dan
 * keluar. Pembatasan per pengguna; platform & standalone serta
 * pengguna tanpa email tidak terhalang.
 */
class EmailVerificationGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, SaasSeeder::class]);
    }

    /** @return array{0: Tenant, 1: Branch, 2: User} */
    private function tenantUser(string $tag, bool $verified): array
    {
        $tenant = Tenant::create(['name' => 'Optik '.$tag, 'slug' => 'optik-'.strtolower($tag), 'email' => strtolower($tag).'@uji.id']);
        $platform = User::where('username', 'platform')->firstOrFail();
        app(PlanService::class)->assignPlan(
            $tenant,
            Plan::where('key', 'best_offer')->firstOrFail(),
            null,
            $platform,
        );
        $branch = Branch::create(['tenant_id' => $tenant->id, 'name' => 'Pusat '.$tag, 'code' => 'PST-'.$tag]);
        $factory = User::factory();
        if (! $verified) {
            $factory = $factory->unverified();
        }
        $user = $factory->create(['tenant_id' => $tenant->id]);
        $user->roles()->sync([Role::where('name', 'Owner')->firstOrFail()->id]);
        $user->branches()->sync([$branch->id]);

        return [$tenant, $branch, $user];
    }

    public function test_signup_sends_verification_and_gates_the_new_owner(): void
    {
        Notification::fake();

        $response = $this->post('/daftar', [
            'business_name' => 'Optik Verifikasi',
            'owner_name' => 'Pemilik Baru',
            'email' => 'baru@uji.id',
            'branch_name' => 'Cabang Satu',
            'password' => 'password-aman-123',
            'password_confirmation' => 'password-aman-123',
        ]);
        $response->assertRedirect(route('dashboard'));

        $user = User::where('email', 'baru@uji.id')->firstOrFail();
        Notification::assertSentTo($user, VerifyEmail::class);
        $this->assertFalse($user->hasVerifiedEmail());

        // Pemilik baru sudah masuk, tetapi gerbang menahannya di
        // halaman verifikasi untuk rute bisnis.
        $this->actingAs($user)->get(route('dashboard'))
            ->assertRedirect(route('verification.notice'));
        $this->actingAs($user)->get(route('verification.notice'))->assertOk();
    }

    public function test_unverified_tenant_user_is_limited_and_resend_works(): void
    {
        [, , $user] = $this->tenantUser('A', verified: false);
        Notification::fake();

        $this->actingAs($user)->get(route('dashboard'))
            ->assertRedirect(route('verification.notice'));
        $this->actingAs($user)->get(route('sales.index'))
            ->assertRedirect(route('verification.notice'));

        $this->actingAs($user)->post(route('verification.send'))
            ->assertRedirect()
            ->assertSessionHas('status', 'verification-link-sent');
        Notification::assertSentTo($user, VerifyEmail::class);

        // Keluar tetap diperbolehkan.
        $this->actingAs($user)->post(route('logout'))->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_signed_link_verifies_and_unlocks_business_access(): void
    {
        [, , $user] = $this->tenantUser('A', verified: false);

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $this->actingAs($user)->get($url)->assertRedirect(route('dashboard'));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->actingAs($user)->get(route('dashboard'))->assertOk();
    }

    public function test_tampered_verification_link_is_rejected(): void
    {
        [, , $user] = $this->tenantUser('A', verified: false);

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1('alamat-lain@uji.id'),
        ]);

        $this->actingAs($user)->get($url)->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_gate_is_per_user_not_per_tenant(): void
    {
        [$tenant, $branch, $owner] = $this->tenantUser('A', verified: true);
        $staff = User::factory()->unverified()->create(['tenant_id' => $tenant->id]);
        $staff->roles()->sync([Role::where('name', 'Owner')->firstOrFail()->id]);
        $staff->branches()->sync([$branch->id]);

        // Pemilik terverifikasi bekerja normal di tenant yang sama…
        $this->actingAs($owner)->get(route('dashboard'))->assertOk();
        // …sementara staf belum terverifikasi tertahan.
        $this->actingAs($staff)->get(route('dashboard'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_platform_user_is_not_gated(): void
    {
        $platform = User::factory()->unverified()->create(['is_platform' => true]);
        $platform->roles()->sync([Role::where('name', 'Platform')->firstOrFail()->id]);

        $this->actingAs($platform)->get(route('platform.tenants.index'))->assertOk();
    }

    public function test_tenant_user_without_email_is_not_gated(): void
    {
        [$tenant, $branch] = $this->tenantUser('A', verified: true);
        $noEmail = User::factory()->unverified()->create(['tenant_id' => $tenant->id, 'email' => null]);
        $noEmail->roles()->sync([Role::where('name', 'Owner')->firstOrFail()->id]);
        $noEmail->branches()->sync([$branch->id]);

        $this->actingAs($noEmail)->get(route('dashboard'))->assertOk();
    }

    public function test_standalone_user_is_not_gated(): void
    {
        // Instalasi mandiri: rezim identitas lama (pengguna dibuat
        // pengelola) tidak boleh dirusak gerbang verifikasi.
        $this->assertSame(0, Tenant::count());
        $owner = User::where('username', 'owner')->firstOrFail();
        $owner->forceFill(['email' => 'mandiri@uji.id', 'email_verified_at' => null])->save();

        $this->actingAs($owner)->get(route('dashboard'))->assertOk();
    }
}
