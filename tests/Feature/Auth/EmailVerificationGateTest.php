<?php

namespace Tests\Feature\Auth;

use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\UserService;
use App\Modules\Master\Models\Branch;
use App\Modules\Membership\Services\MemberPortalService;
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

    public function test_profile_email_change_revokes_verification_and_notifies_new_address(): void
    {
        [, , $user] = $this->tenantUser('A', verified: true);
        Notification::fake();

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => 'alamat-baru@uji.id',
        ])->assertRedirect(route('profile.edit'));

        $fresh = $user->fresh();
        $this->assertSame('alamat-baru@uji.id', $fresh->email);
        $this->assertFalse($fresh->hasVerifiedEmail());
        // Notifikasi terkirim ke model yang kini beralamat BARU.
        Notification::assertSentTo($fresh, VerifyEmail::class);

        // Akses bisnis kembali tertahan sampai alamat baru terbukti.
        $this->actingAs($fresh)->get(route('dashboard'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_profile_update_without_email_change_keeps_verification(): void
    {
        [, , $user] = $this->tenantUser('A', verified: true);
        Notification::fake();

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => 'Nama Diperbarui',
            'email' => $user->email,
        ])->assertRedirect(route('profile.edit'));

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        Notification::assertNotSentTo($user, VerifyEmail::class);
        $this->actingAs($user)->get(route('dashboard'))->assertOk();
    }

    public function test_admin_email_change_revokes_verification_and_notifies(): void
    {
        [$tenant, $branch, $admin] = $this->tenantUser('A', verified: true);
        $target = User::factory()->create(['tenant_id' => $tenant->id, 'email' => 'sasaran@uji.id']);
        $target->roles()->sync([Role::where('name', 'Kasir')->firstOrFail()->id]);
        $target->branches()->sync([$branch->id]);
        Notification::fake();

        // Jalur pengelolaan pengguna tenant (UserController
        // meneruskan ke layanan ini).
        app(UserService::class)->updateUser($target, ['email' => 'sasaran-baru@uji.id'], $admin);

        $fresh = $target->fresh();
        $this->assertSame('sasaran-baru@uji.id', $fresh->email);
        $this->assertFalse($fresh->hasVerifiedEmail());
        Notification::assertSentTo($fresh, VerifyEmail::class);
        $this->actingAs($fresh)->get(route('dashboard'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_changed_email_can_be_verified_via_signed_link(): void
    {
        [, , $user] = $this->tenantUser('A', verified: true);

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => 'terbukti@uji.id',
        ]);
        $user = $user->fresh();
        $this->assertFalse($user->hasVerifiedEmail());

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1('terbukti@uji.id'),
        ]);
        $this->actingAs($user)->get($url)->assertRedirect(route('dashboard'));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->actingAs($user)->get(route('dashboard'))->assertOk();
    }

    public function test_unverified_session_can_open_member_portal_and_webhook_but_not_business(): void
    {
        [$tenant, $branch, $unverified] = $this->tenantUser('A', verified: false);
        $customer = Customer::factory()->create([
            'tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'name' => 'Pelanggan Portal',
        ]);
        $token = app(MemberPortalService::class)->issueToken($customer);

        // Portal publik ber-token tidak bergantung pada status
        // verifikasi sesi staf yang kebetulan masuk.
        $this->actingAs($unverified)->get('/m/'.$token)
            ->assertOk()->assertSee('Pelanggan Portal');
        // Token tidak valid tetap ditolak seperti sebelumnya.
        $this->actingAs($unverified)->get('/m/token-ngawur')->assertNotFound();
        // Webhook publik menjawab dengan gerbang tokennya sendiri
        // (403), bukan dialihkan ke halaman verifikasi.
        $this->actingAs($unverified)->get('/wa/webhook')->assertForbidden();
        // Rute bisnis tetap tertahan.
        $this->actingAs($unverified)->get(route('dashboard'))
            ->assertRedirect(route('verification.notice'));
    }
}
