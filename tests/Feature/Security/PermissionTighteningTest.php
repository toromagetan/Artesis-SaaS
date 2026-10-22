<?php

namespace Tests\Feature\Security;

use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use App\Modules\Purchasing\Models\PurchaseRequest;
use App\Modules\Purchasing\Services\PurchaseRequestService;
use App\Modules\Sales\Models\OfflineDevice;
use App\Modules\Sales\Models\OfflineException;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit 23 Okt 2026 (A3): mutasi berat tidak lagi berpagar izin
 * level-lihat/buat — template QC (lab.templates.manage), batal PR
 * (pr.cancel), buang pengecualian offline (sales.exceptions).
 * Admin memegang ketiganya; peran operasional tidak. Butir A6
 * (Asisten AI) terbukti positif palsu saat verifikasi — asisten
 * memang owner-only sejak F5.1; tes terakhir mengunci desain itu.
 */
class PermissionTighteningTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $admin;

    private User $kasir;

    private User $teknisi;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IdentitySeeder::class);
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $this->branch = Branch::where('name', 'Pusat')->firstOrFail();
        $this->admin = $this->userWithRole('Admin');
        $this->kasir = $this->userWithRole('Kasir');
        $this->teknisi = $this->userWithRole('Teknisi');
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->sync([Role::where('name', $role)->firstOrFail()->id]);
        $user->branches()->sync([$this->branch->id]);

        return $user;
    }

    public function test_qc_templates_manage_requires_dedicated_permission(): void
    {
        // Teknisi melihat daftar (lab.view) tetapi tak dapat mengubah.
        $this->actingAs($this->teknisi)->get(route('qc-templates.index'))->assertOk();
        $this->actingAs($this->teknisi)->post(route('qc-templates.store'), [
            'name' => 'Titik liar', 'sort_order' => 99,
        ])->assertForbidden();

        $this->actingAs($this->admin)->post(route('qc-templates.store'), [
            'name' => 'Titik resmi', 'sort_order' => 30,
        ])->assertRedirect(route('qc-templates.index'));
    }

    public function test_pr_cancel_requires_dedicated_permission(): void
    {
        $product = Product::factory()->create();
        $pr = app(PurchaseRequestService::class)->create($this->owner, $this->branch->id, [
            ['product_id' => $product->id, 'qty' => 1],
        ]);
        $this->assertInstanceOf(PurchaseRequest::class, $pr);

        $this->actingAs($this->kasir)->post(route('pr.cancel', $pr))->assertForbidden();
        $this->actingAs($this->admin)->from(route('pr.show', $pr))->post(route('pr.cancel', $pr))
            ->assertRedirect(route('pr.show', $pr));
        $this->assertSame('cancelled', $pr->fresh()->status);
    }

    public function test_offline_exception_discard_requires_dedicated_permission(): void
    {
        $device = OfflineDevice::create([
            'name' => 'Kasir Uji', 'branch_id' => $this->branch->id,
            'user_id' => $this->kasir->id, 'token_hash' => hash('sha256', 'token-uji'),
            'created_by' => $this->owner->id,
        ]);
        $exception = OfflineException::create([
            'offline_device_id' => $device->id,
            'branch_id' => $this->branch->id, 'client_uuid' => 'uuid-tighten-1',
            'local_no' => 'OFF-1', 'payload' => ['items' => []], 'reason' => 'stok',
            'status' => 'pending',
        ]);

        $this->actingAs($this->kasir)->post(route('offline-exceptions.discard', $exception), [
            'note' => 'coba buang',
        ])->assertForbidden();
        $this->actingAs($this->admin)->post(route('offline-exceptions.discard', $exception), [
            'note' => 'Pelanggan batal.',
        ])->assertRedirect(route('offline-exceptions.index'));
        $this->assertSame('discarded', $exception->fresh()->status);
    }

    /** Koreksi atas temuan audit A6: Asisten AI ternyata SUDAH
     *  owner-only sejak F5.1 — index memeran Owner di controller dan
     *  ask memeran Owner di service. Tes ini mengunci perilaku itu
     *  agar tidak regresi (bukan izin khusus). */
    public function test_assistant_is_owner_only_by_design(): void
    {
        $this->actingAs($this->kasir)->get(route('assistant.index'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('assistant.index'))->assertForbidden();
        $this->actingAs($this->admin)->post(route('assistant.ask'), [
            'question' => 'Berapa penjualan hari ini?',
        ])->assertForbidden();

        $this->actingAs($this->owner)->get(route('assistant.index'))->assertOk();
    }
}
