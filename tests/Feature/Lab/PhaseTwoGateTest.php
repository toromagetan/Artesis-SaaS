<?php

namespace Tests\Feature\Lab;

use App\Modules\Accounting\Models\Journal;
use App\Modules\Catalog\Models\Product;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Lab\Models\LensProcess;
use App\Modules\Lab\Models\QcCheck;
use App\Modules\Lab\Models\QcPointTemplate;
use App\Modules\Lab\Services\LabOrderService;
use App\Modules\Lab\Services\LensProcessService;
use App\Modules\Lab\Services\QcCheckService;
use App\Modules\Master\Models\Branch;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Sales\Services\SalesService;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\QcPointTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * GERBANG KELUAR FASE 2 (Roadmap §8): satu orderan uji ujung-ke-ujung —
 * jual → edging → QC gagal 1 titik → perbaiki → lulus → order lab →
 * received → Complete — tercatat lengkap dengan teknisi & waktu,
 * dan biaya lab berakhir di jurnal HPP.
 */
class PhaseTwoGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_end_to_end_order_with_qc_failure_recovery_lab_order_and_completion(): void
    {
        $this->seed([IdentitySeeder::class, AccountingSeeder::class, QcPointTemplateSeeder::class]);
        $owner = User::where('username', 'owner')->firstOrFail();
        $branch = Branch::where('name', 'Pusat')->firstOrFail();
        $customer = Customer::factory()->create(['branch_id' => $branch->id]);
        $rx = Product::factory()->ofType('rx')->create(['sale_price' => '450000']);

        $technician = User::create([
            'name' => 'Bimo Teknisi', 'username' => 'bimo', 'password' => 'password', 'is_active' => true,
        ]);
        $technician->roles()->attach(Role::where('name', 'Teknisi')->firstOrFail());
        $technician->branches()->attach($branch->id);

        // 1) Jual → proses lab lahir berstatus waiting.
        $sale = app(SalesService::class)->createSale($owner, [
            'customer_id' => $customer->id, 'sale_date' => '2026-10-18', 'tax_mode' => 'inclusive',
        ], [
            ['product_id' => $rx->id, 'qty' => 1, 'prescription_ref' => 'pair'],
        ]);
        $process = LensProcess::where('sale_id', $sale->id)->firstOrFail();
        $this->assertSame('waiting', $process->status);

        // 2) Tugaskan teknisi + maju ke edging lalu qc.
        $lab = app(LensProcessService::class);
        $lab->assignTechnician($owner, $process, $technician->id, 3);
        $lab->transition($owner, $process, 'edging');
        $lab->transition($owner, $process, 'qc');
        $this->assertSame('qc', $process->refresh()->status);

        // 3) QC: satu titik GAGAL ber-catatan; titik lain lulus.
        $qc = app(QcCheckService::class);
        $points = QcPointTemplate::activeOrdered()->get();
        $failingPoint = $points->first();
        foreach ($points as $point) {
            if ($point->id === $failingPoint->id) {
                $qc->record($technician, $process, $point->id, 'fail', 'Axis meleset 5 derajat, ulangi edging');
            } else {
                $qc->record($technician, $process, $point->id, 'pass');
            }
        }

        // 4) Complete saat ini DITOLAK (gerbang negatif Fase 2).
        try {
            $lab->complete($owner, $process);
            $this->fail('Complete dengan titik QC gagal seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('qc', $e->errors());
        }

        // 5) Perbaiki: titik gagal diperiksa ulang → lulus (percobaan 2).
        $fixed = $qc->record($technician, $process, $failingPoint->id, 'pass', 'Sudah diperbaiki');
        $this->assertSame(2, $fixed->attempt);
        $this->assertSame(14, QcCheck::where('lens_process_id', $process->id)->count());

        // 6) Order lab: buat → dipesan → diterima dengan biaya final.
        $orders = app(LabOrderService::class);
        $order = $orders->create($owner, $sale, 'Lab Sentral Jakarta', '60000');
        $orders->transition($owner, $order, 'ordered');
        $orders->transition($owner, $order, 'received', '65000');
        $order->refresh();
        $this->assertSame('received', $order->status);
        $this->assertNotNull($order->sent_at);
        $this->assertNotNull($order->received_at);

        // Jurnal biaya lab → HPP tercatat.
        $journal = Journal::where('source_type', 'lab_order')->where('source_id', $order->id)->firstOrFail();
        $debit = $journal->lines()->with('account')->get()->firstWhere('account.code', '5-1100');
        $this->assertSame('65000.00', (string) $debit->debit);

        // 7) Maju ke ready lalu Complete — kini berhasil, teknisi & waktu tercatat.
        $lab->transition($owner, $process, 'ready');
        $done = $lab->complete($owner, $process);
        $this->assertSame('done', $done->status);
        $this->assertSame($technician->id, $done->technician_id);
        $this->assertNotNull($done->completed_at);
        $this->assertNotNull($done->started_at);
        $this->assertTrue($done->completed_at->greaterThanOrEqualTo($done->started_at));

        // Jejak audit lengkap untuk rantai peristiwa lab.
        foreach (['lab.assign', 'lab.qc_record', 'lab_order.create', 'lab_order.transition', 'lab.complete'] as $action) {
            $this->assertTrue(
                AuditLog::where('action', $action)->exists(),
                "Jejak audit {$action} tidak ditemukan.",
            );
        }

        // Pemeriksaan QC tercatat atas nama teknisi pemeriksa.
        $this->assertTrue(
            QcCheck::where('lens_process_id', $process->id)
                ->where('checked_by', $technician->id)
                ->exists(),
        );
    }
}
