<?php

namespace Tests\Feature\Lab;

use App\Modules\Catalog\Models\Product;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Lab\Models\LensProcess;
use App\Modules\Lab\Services\LensProcessService;
use App\Modules\Master\Models\Branch;
use App\Modules\Sales\Services\SalesService;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * M.5 Lab dasar (Blueprint D.6/D.7 + E.3): pembuatan proses dari
 * penjualan lensa, mesin status linear, complete() sekali tulis,
 * durasi membeku ke completed_at.
 */
class LensProcessTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Branch $branch;

    private Customer $customer;

    private Product $lensRx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IdentitySeeder::class);
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $this->branch = Branch::where('name', 'Pusat')->firstOrFail();
        $this->customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $this->lensRx = Product::factory()->ofType('rx')->create(['sale_price' => '300000']);
    }

    private function lensSale(string $date = '2026-10-17')
    {
        return app(SalesService::class)->createSale($this->owner, [
            'customer_id' => $this->customer->id,
            'sale_date' => $date,
            'tax_mode' => 'inclusive',
        ], [
            ['product_id' => $this->lensRx->id, 'qty' => 1, 'prescription_ref' => 'pair'],
        ]);
    }

    public function test_lens_sale_creates_waiting_process_frame_only_does_not(): void
    {
        $sale = $this->lensSale();
        $process = LensProcess::where('sale_id', $sale->id)->first();
        $this->assertNotNull($process);
        $this->assertSame('waiting', $process->status);
        $this->assertNotNull($process->started_at);

        // ensureForSale idempoten (sale_id UNIQUE).
        app(LensProcessService::class)->ensureForSale($sale);
        $this->assertSame(1, LensProcess::where('sale_id', $sale->id)->count());

        // Penjualan frame saja tanpa item lensa: tidak ada proses.
        $frame = Product::factory()->ofType('frame')->create(['sale_price' => '100000']);
        app(InventoryService::class)->recordMovement(
            $this->owner, $frame, $this->branch, 'initial_stock', 3, 'test_seed', 1,
        );
        $frameSale = app(SalesService::class)->createSale($this->owner, [
            'customer_id' => $this->customer->id, 'tax_mode' => 'inclusive',
        ], [
            ['product_id' => $frame->id, 'qty' => 1],
        ]);
        $this->assertNull(LensProcess::where('sale_id', $frameSale->id)->first());
    }

    public function test_assign_and_linear_transitions(): void
    {
        $sale = $this->lensSale();
        $process = LensProcess::where('sale_id', $sale->id)->firstOrFail();
        $service = app(LensProcessService::class);

        $teknisi = User::factory()->create();
        $teknisi->roles()->sync([Role::where('name', 'teknisi')->firstOrFail()->id]);
        $service->assignTechnician($this->owner, $process, $teknisi->id, 3);
        $this->assertSame($teknisi->id, $process->refresh()->technician_id);
        $this->assertSame(3, $process->estimated_days);

        // Lompatan waiting → qc ditolak; langkah linear diterima.
        try {
            $service->transition($this->owner, $process, 'qc');
            $this->fail('Lompatan status harus ditolak');
        } catch (ValidationException) {
            $this->assertSame('waiting', $process->refresh()->status);
        }
        $service->transition($this->owner, $process, 'edging');
        $service->transition($this->owner, $process, 'qc');
        $service->transition($this->owner, $process, 'ready');
        $this->assertSame('ready', $process->refresh()->status);

        // Mundur ditolak.
        $this->expectException(ValidationException::class);
        $service->transition($this->owner, $process, 'edging');
    }

    public function test_complete_writes_completed_at_once_and_duration_freezes(): void
    {
        $sale = $this->lensSale('2026-10-10');
        $process = LensProcess::where('sale_id', $sale->id)->firstOrFail();
        $service = app(LensProcessService::class);

        Carbon::setTestNow('2026-10-13 10:00:00');
        $done = $service->complete($this->owner, $process);
        $this->assertSame('done', $done->status);
        $this->assertSame('2026-10-13 10:00:00', $done->completed_at->format('Y-m-d H:i:s'));
        $this->assertSame(3, $done->durationDays()); // 10 → 13 Okt = 3 hari kalender

        // Waktu berjalan: durasi membeku; complete kedua ditolak.
        Carbon::setTestNow('2026-10-20 10:00:00');
        $this->assertSame(3, $process->refresh()->durationDays());
        try {
            $service->complete($this->owner, $process);
            $this->fail('Complete kedua harus ditolak');
        } catch (ValidationException) {
            $this->assertSame('2026-10-13 10:00:00', $process->refresh()->completed_at->format('Y-m-d H:i:s'));
        }
        Carbon::setTestNow();
    }

    public function test_void_blocked_after_done_and_removes_unfinished_process(): void
    {
        // Proses belum done: void membuang antrean.
        $saleA = $this->lensSale();
        app(SalesService::class)->voidSale($this->owner, $saleA, 'Batal A');
        $this->assertSame(0, LensProcess::where('sale_id', $saleA->id)->count());

        // Proses done: void penjualan DITOLAK (syarat D.6 — harus lewat retur).
        $saleB = $this->lensSale();
        $processB = LensProcess::where('sale_id', $saleB->id)->firstOrFail();
        app(LensProcessService::class)->complete($this->owner, $processB);
        $this->expectException(ValidationException::class);
        app(SalesService::class)->voidSale($this->owner, $saleB, 'Batal B');
    }

    public function test_http_queue_permissions_and_branch_fencing(): void
    {
        $sale = $this->lensSale();
        $process = LensProcess::where('sale_id', $sale->id)->firstOrFail();

        $this->actingAs($this->owner)->get('/lab')->assertOk()->assertSee($sale->number);
        $this->actingAs($this->owner)->get('/lab/'.$process->id)->assertOk();

        // Matriks C.2 nyata: Kasir MEMEGANG lab.view (pantau status untuk
        // pelanggan) tetapi bukan lab.complete; Gudang tanpa lab.* → 403.
        $kasir = User::factory()->create();
        $kasir->roles()->sync([Role::where('name', 'kasir')->firstOrFail()->id]);
        $kasir->branches()->sync([$this->branch->id]);
        $this->actingAs($kasir)->get('/lab')->assertOk()->assertSee($sale->number);
        $this->actingAs($kasir)->post('/lab/'.$process->id.'/selesai')->assertForbidden();

        $gudang = User::factory()->create();
        $gudang->roles()->sync([Role::where('name', 'gudang')->firstOrFail()->id]);
        $gudang->branches()->sync([$this->branch->id]);
        $this->actingAs($gudang)->get('/lab')->assertForbidden();

        // Teknisi cabang lain tidak melihat proses ini (404).
        $branchB = Branch::create(['name' => 'Cabang B']);
        $teknisiB = User::factory()->create();
        $teknisiB->roles()->sync([Role::where('name', 'teknisi')->firstOrFail()->id]);
        $teknisiB->branches()->sync([$branchB->id]);
        $this->actingAs($teknisiB)->get('/lab/'.$process->id)->assertNotFound();
        $this->actingAs($teknisiB)->get('/lab')->assertOk()->assertDontSee($sale->number);
    }
}
