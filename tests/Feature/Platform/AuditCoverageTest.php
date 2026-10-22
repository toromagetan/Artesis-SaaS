<?php

namespace Tests\Feature\Platform;

use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Sales\Models\OfflineDevice;
use App\Modules\Sales\Models\OfflineException;
use App\Modules\Sales\Services\OfflineSyncService;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit 23 Okt 2026 (A4): empat alur mutasi yang sebelumnya tanpa
 * jejak audit kini mencatat — buang pengecualian offline, terbit &
 * cabut token portal member, dan rekam fitting. (Clawback insentif
 * diasertakan di IncentiveTest pada skenario pembuatnya.)
 */
class AuditCoverageTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IdentitySeeder::class);
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $this->branch = Branch::where('name', 'Pusat')->firstOrFail();
    }

    public function test_offline_discard_is_audit_logged(): void
    {
        $device = OfflineDevice::create([
            'name' => 'Kasir Uji', 'branch_id' => $this->branch->id,
            'user_id' => $this->owner->id, 'token_hash' => hash('sha256', 'token-uji'),
            'created_by' => $this->owner->id,
        ]);
        $exception = OfflineException::create([
            'offline_device_id' => $device->id, 'branch_id' => $this->branch->id,
            'client_uuid' => 'uuid-audit-1', 'local_no' => 'OFF-9',
            'payload' => ['items' => []], 'reason' => 'stok', 'status' => 'pending',
        ]);

        app(OfflineSyncService::class)->discard($this->owner, $exception, 'Pelanggan batal.');

        $this->assertTrue(AuditLog::where('action', 'offline.exception.discard')
            ->where('subject_id', $exception->id)->exists());
    }

    public function test_portal_token_issue_and_revoke_are_audit_logged(): void
    {
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);

        $this->actingAs($this->owner)->post(route('members.portal.issue', $customer))
            ->assertRedirect(route('members.index'));
        $this->actingAs($this->owner)->post(route('members.portal.revoke', $customer))
            ->assertRedirect(route('members.index'));

        $this->assertTrue(AuditLog::where('action', 'member.portal_token.issue')
            ->where('subject_id', $customer->id)->exists());
        $this->assertTrue(AuditLog::where('action', 'member.portal_token.revoke')
            ->where('subject_id', $customer->id)->exists());
    }

    public function test_fitting_record_is_audit_logged(): void
    {
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);

        $this->actingAs($this->owner)->post(route('fittings.store', $customer), [
            'method' => 'manual', 'pd_reference' => 63,
        ])->assertRedirect(route('fittings.measure', $customer));

        $this->assertTrue(AuditLog::where('action', 'clinical.fitting.record')
            ->where('user_id', $this->owner->id)->exists());
    }
}
