<?php

namespace Tests\Feature\Lab;

use App\Modules\Catalog\Models\Product;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Lab\Models\LensProcess;
use App\Modules\Lab\Models\QcCheck;
use App\Modules\Lab\Models\QcPointTemplate;
use App\Modules\Lab\Services\LensProcessService;
use App\Modules\Lab\Services\QcCheckService;
use App\Modules\Lab\Services\QcTemplateService;
use App\Modules\Master\Models\Branch;
use App\Modules\Sales\Services\SalesService;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\QcPointTemplateSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * F2.2 — Perekaman QC per percobaan + gerbang Complete.
 * Gerbang Fase 2 (Roadmap): Complete DITOLAK selama ada titik wajib
 * yang belum Lulus — untuk jalur service DAN jalur UI (HTTP).
 */
class QcRecordingTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $technician;

    private Branch $branch;

    private Customer $customer;

    private Product $lensRx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, AccountingSeeder::class, QcPointTemplateSeeder::class]);
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $this->branch = Branch::where('name', 'Pusat')->firstOrFail();
        $this->customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $this->lensRx = Product::factory()->ofType('rx')->create(['sale_price' => '300000']);
        $this->technician = User::create([
            'name' => 'Teknisi Uji', 'username' => 'teknisiuji', 'password' => 'password', 'is_active' => true,
        ]);
        $this->technician->roles()->attach(Role::where('name', 'Teknisi')->firstOrFail());
        // Teknisi bertugas di cabang Pusat (pagar cabang HTTP berlaku).
        $this->technician->branches()->attach($this->branch->id);
    }

    private function newProcess(): LensProcess
    {
        $sale = app(SalesService::class)->createSale($this->owner, [
            'customer_id' => $this->customer->id,
            'sale_date' => '2026-10-18',
            'tax_mode' => 'inclusive',
        ], [
            ['product_id' => $this->lensRx->id, 'qty' => 1, 'prescription_ref' => 'pair'],
        ]);

        return LensProcess::where('sale_id', $sale->id)->firstOrFail();
    }

    private function passAllRequired(LensProcess $process, ?array $exceptIds = null): void
    {
        $qc = app(QcCheckService::class);
        foreach (QcPointTemplate::activeOrdered()->where('is_required', true)->get() as $point) {
            if ($exceptIds !== null && in_array($point->id, $exceptIds, true)) {
                continue;
            }
            $qc->record($this->technician, $process, $point->id, 'pass');
        }
    }

    public function test_recording_attempts_history_and_fail_notes_rule(): void
    {
        $process = $this->newProcess();
        $qc = app(QcCheckService::class);
        $point = QcPointTemplate::activeOrdered()->firstOrFail();

        // Gagal TANPA catatan → ditolak.
        try {
            $qc->record($this->technician, $process, $point->id, 'fail');
            $this->fail('Hasil gagal tanpa catatan seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('notes', $e->errors());
        }

        $fail = $qc->record($this->technician, $process, $point->id, 'fail', 'Axis meleset 4 derajat');
        $this->assertSame(1, $fail->attempt);
        $pass = $qc->record($this->technician, $process, $point->id, 'pass');
        $this->assertSame(2, $pass->attempt);

        // Riwayat tidak ditimpa: dua baris tersimpan; hasil terakhir = pass.
        $this->assertSame(2, QcCheck::where('lens_process_id', $process->id)->count());
        $latest = $qc->latestByPoint($process);
        $this->assertSame('pass', $latest[$point->id]->result);
        $this->assertSame($this->technician->id, $latest[$point->id]->checked_by);
    }

    public function test_complete_is_blocked_until_every_required_point_passes(): void
    {
        $process = $this->newProcess();
        $lab = app(LensProcessService::class);

        // Jalur service: belum ada QC sama sekali → ditolak.
        try {
            $lab->complete($this->owner, $process);
            $this->fail('Complete tanpa QC wajib seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('qc', $e->errors());
        }

        // Jalur UI (HTTP): redirect kembali dengan galat sesi, proses belum done.
        $this->actingAs($this->owner)
            ->post(route('lab.complete', $process))
            ->assertSessionHasErrors('qc');
        $this->assertSame('waiting', $process->refresh()->status);

        // Semua titik kecuali satu lulus → tetap ditolak.
        $lastPoint = QcPointTemplate::activeOrdered()->where('is_required', true)->orderByDesc('sort_order')->firstOrFail();
        $this->passAllRequired($process, [$lastPoint->id]);
        try {
            $lab->complete($this->owner, $process);
            $this->fail('Complete dengan satu titik wajib belum lulus seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('qc', $e->errors());
        }

        // Titik terakhir gagal (percobaan terakhir bukan pass) → tetap ditolak.
        app(QcCheckService::class)->record($this->technician, $process, $lastPoint->id, 'fail', 'Bevel kurang rapi');
        try {
            $lab->complete($this->owner, $process);
            $this->fail('Complete dengan titik wajib terakhir Gagal seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('qc', $e->errors());
        }

        // Perbaiki → lulus → Complete berhasil; pemanggilan kedua ditolak.
        app(QcCheckService::class)->record($this->technician, $process, $lastPoint->id, 'pass');
        $done = $lab->complete($this->owner, $process);
        $this->assertSame('done', $done->status);
        $this->assertNotNull($done->completed_at);
        try {
            $lab->complete($this->owner, $process);
            $this->fail('Complete kedua seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }
    }

    public function test_inactive_and_optional_points_do_not_block_completion(): void
    {
        $process = $this->newProcess();
        $templates = app(QcTemplateService::class);
        $points = QcPointTemplate::activeOrdered()->where('is_required', true)->get();

        // Titik pertama dinonaktifkan; titik kedua dijadikan tidak wajib.
        $templates->setActive($this->owner, $points[0], false);
        $templates->update($this->owner, $points[1], $points[1]->name, false, $points[1]->sort_order);

        $this->passAllRequired($process, [$points[0]->id, $points[1]->id]);
        $done = app(LensProcessService::class)->complete($this->owner, $process);
        $this->assertSame('done', $done->status);
    }

    public function test_recording_requires_qc_permission_and_open_process(): void
    {
        $process = $this->newProcess();
        $point = QcPointTemplate::activeOrdered()->firstOrFail();
        $qc = app(QcCheckService::class);

        $kasir = User::create(['name' => 'Kasir Uji', 'username' => 'kasiruji', 'password' => 'password', 'is_active' => true]);
        $kasir->roles()->attach(Role::where('name', 'Kasir')->firstOrFail());
        try {
            $qc->record($kasir, $process, $point->id, 'pass');
            $this->fail('Kasir tanpa lab.qc seharusnya ditolak mencatat QC.');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }
        $this->actingAs($kasir)
            ->post(route('lab.qc.record', $process), ['point_id' => $point->id, 'result' => 'pass'])
            ->assertForbidden();

        // Lewat HTTP oleh teknisi: tercatat dan tampak di halaman detail.
        $this->actingAs($this->technician)
            ->post(route('lab.qc.record', $process), ['point_id' => $point->id, 'result' => 'pass'])
            ->assertRedirect();
        $this->actingAs($this->owner)->get(route('lab.show', $process))
            ->assertOk()->assertSee($point->name)->assertSee('Lulus');

        // Proses done terkunci dari perekaman baru.
        $this->passAllRequired($process);
        app(LensProcessService::class)->complete($this->owner, $process);
        try {
            $qc->record($this->technician, $process, $point->id, 'pass');
            $this->fail('Perekaman pada proses done seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('process', $e->errors());
        }
    }
}
