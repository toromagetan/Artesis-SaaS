<?php

namespace Tests\Feature\Auth;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Platform\Models\AuditLog;
use Database\Seeders\IdentitySeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Pemulihan istimewa: hanya Owner AKTIF yang benar-benar
 * menerima token reset; semua respons identik generik
 * (anti-enumerasi); setiap percobaan teraudit; token yang
 * terbit dapat dipakai pada alur reset standar.
 */
class PrivilegedRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IdentitySeeder::class);
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $this->owner->forceFill(['email' => 'owner@istori.test'])->save();
        Notification::fake();
    }

    public function test_owner_receives_reset_and_can_use_it(): void
    {
        $response = $this->post(route('recovery.email'), ['email' => 'owner@istori.test']);
        $response->assertSessionHas('status', __('ui.recovery.generic_status'));

        $token = null;
        Notification::assertSentTo($this->owner, ResetPassword::class,
            function (ResetPassword $notification) use (&$token) {
                $token = $notification->token;

                return true;
            });
        $this->assertNotNull($token);
        $this->assertTrue(AuditLog::where('action', 'recovery.requested')
            ->where('subject_id', $this->owner->id)->exists());

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => 'owner@istori.test',
            'password' => 'SandiBaru#2026',
            'password_confirmation' => 'SandiBaru#2026',
        ])->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('SandiBaru#2026', $this->owner->fresh()->password));
    }

    public function test_non_owner_and_unknown_get_generic_without_notification(): void
    {
        $kasir = User::factory()->create(['email' => 'kasir@istori.test', 'is_active' => true]);
        $kasir->roles()->sync([Role::where('name', 'Kasir')->firstOrFail()->id]);

        $this->post(route('recovery.email'), ['email' => 'kasir@istori.test'])
            ->assertSessionHas('status', __('ui.recovery.generic_status'));
        Notification::assertNotSentTo($kasir, ResetPassword::class);

        $this->post(route('recovery.email'), ['email' => 'takada@istori.test'])
            ->assertSessionHas('status', __('ui.recovery.generic_status'));
        Notification::assertNothingSent();
    }

    public function test_inactive_owner_gets_nothing(): void
    {
        $this->owner->forceFill(['is_active' => false])->save();
        $this->post(route('recovery.email'), ['email' => 'owner@istori.test'])
            ->assertSessionHas('status', __('ui.recovery.generic_status'));
        Notification::assertNothingSent();
    }

    public function test_recovery_page_is_public(): void
    {
        $this->get(route('recovery.request'))->assertOk();
    }
}
