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
use App\Modules\Lab\Services\LensProcessService;
use App\Modules\Lab\Services\ProcurementStatusService;
use App\Modules\Lab\Services\QcCheckService;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Supplier;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Purchasing\Models\PurchaseRequest;
use App\Modules\Purchasing\Services\PurchaseOrderService;
use App\Modules\Purchasing\Services\PurchaseRequestService;
use App\Modules\Purchasing\Services\ReceiptService;
use App\Modules\Sales\Services\SalesService;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\QcPointTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * GERBANG KELUAR FASE 2 (Roadmap §8, direvisi koreksi owner 2026-10-18
 * mengikuti rantai ARTESIS): jual RX -> PR otomatis -> PO -> terima ->
 * edging -> QC gagal 1 titik -> Complete DITOLAK -> perbaiki -> lulus
 * -> Complete tercatat lengkap dengan teknisi & waktu. Order lab
 * BUKAN dokumen tersendiri: pekerjaan lab eksternal berjalan lewat
 * PR/PO; TIDAK ada jurnal sumber 'lab_order'.
 */
class PhaseTwoGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_end_to_end_artesis_chain_with_qc_failure_recovery_and_completion(): void
    {
        $this->seed([IdentitySeeder::class, AccountingSeeder::class, QcPointTemplateSeeder::class]);
        $owner = User::where('username', 'owner')->firstOrFail();
        $branch = Branch::where('name', 'Pusat')->firstOrFail();
        $customer = Customer::factory()->create(['branch_id' => $branch->id]);
        $supplier = Supplier::create(['name' => 'PT Lab Uji', 'is_active' => true]);
        $rx = Product::factory()->ofType('rx')->create(['sale_price' => '450000']);

        $technician = User::create([
            'name' => 'Bimo Teknisi', 'username' => 'bimo', 'password' => 'password', 'is_active' => true,
        ]);
        $technician->roles()->attach(Role::where('name', 'Teknisi')->firstOrFail());
        $technician->branches()->attach($branch->id);

        // 1) Jual RX -> proses waiting + PR otomatis tertaut (submitted).
        $sale = app(SalesService::class)->createSale($owner, [
            'customer_id' => $customer->id, 'sale_date' => '2026-10-18', 'tax_mode' => 'inclusive',
            // ADR-019: item RX mewajibkan resep pelanggan yang sama.
            'prescription_id' => \App\Modules\Clinical\Models\Prescription::factory()->create(['customer_id' => $customer->id])->id,
        ], [
            ['product_id' => $rx->id, 'qty' => 1, 'prescription_ref' => 'pair'],
        ]);
        $process = LensProcess::where('sale_id', $sale->id)->firstOrFail();
        $this->assertSame('waiting', $process->status);
        $pr = PurchaseRequest::where('sale_id', $sale->id)->firstOrFail();
        $this->assertSame('submitted', $pr->status);
        $stage = app(ProcurementStatusService::class);
        $this->assertSame('pr', $stage->stageFor($sale));

        $lab = app(LensProcessService::class);
        $lab->assignTechnician($owner, $process, $technician->id, 3);

        // 2) Edging DITOLAK sebelum lensa diterima (gerbang urutan).
        try {
            $lab->transition($owner, $process, 'edging');
            $this->fail('Edging harus ditolak sebelum penerimaan lensa');
        } catch (ValidationException) {
            $this->assertSame('waiting', $process->refresh()->status);
        }

        // 3) Rantai pengadaan: PR -> PO -> kirim -> terima penuh.
        $prs = app(PurchaseRequestService::class);
        $prs->approve($owner, $pr);
        $po = $prs->convertToPo($owner, [$pr->refresh()], $supplier->id);
        app(PurchaseOrderService::class)->markSent($owner, $po);
        $poItem = $po->items()->firstOrFail();
        app(ReceiptService::class)->store($owner, $po->refresh(), [
            ['purchase_order_item_id' => $poItem->id, 'qty' => 1, 'unit_cost_actual' => '300000'],
        ]);
        $this->assertSame('complete', $po->refresh()->status);
        $this->assertSame('received', $stage->stageFor($sale));

        // 4) Edging -> qc sesudah lensa diterima.
        $lab->transition($owner, $process, 'edging');
        $lab->transition($owner, $process, 'qc');
        $this->assertSame('qc', $process->refresh()->status);

        // 5) QC: satu titik GAGAL ber-catatan; titik lain lulus.
        $qc = app(QcCheckService::class);
        $points = QcPointTemplate::activeOrdered()->get();
        $this->assertCount(13, $points);
        $failingPoint = $points->first();
        foreach ($points as $point) {
            if ($point->id === $failingPoint->id) {
                $qc->record($technician, $process, $point->id, 'fail', 'Axis meleset 5 derajat, ulangi edging');
            } else {
                $qc->record($technician, $process, $point->id, 'pass');
            }
        }

        // 6) Complete DITOLAK selama titik wajib terakhirnya gagal.
        try {
            $lab->complete($owner, $process);
            $this->fail('Complete harus ditolak selama QC wajib belum lulus');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('qc', $e->errors());
        }
        $this->assertFalse($process->refresh()->isDone());

        // 7) Perbaiki: periksa ulang titik gagal -> lulus (percobaan 2).
        $qc->record($technician, $process, $failingPoint->id, 'pass', 'Axis sudah tepat sesudah edging ulang');
        $this->assertSame(2, QcCheck::where('lens_process_id', $process->id)
            ->where('qc_point_template_id', $failingPoint->id)->max('attempt'));
        $this->assertSame(14, QcCheck::where('lens_process_id', $process->id)->count());

        // 8) Ready -> Complete; tercatat teknisi & waktu; tahap 'done'.
        $lab->transition($owner, $process, 'ready');
        $lab->complete($owner, $process);
        $process->refresh();
        $this->assertTrue($process->isDone());
        $this->assertSame($technician->id, (int) $process->technician_id);
        $this->assertNotNull($process->completed_at);
        $this->assertTrue($process->completed_at->greaterThanOrEqualTo($process->started_at));
        $this->assertSame('done', $stage->stageFor($sale));

        // 9) Jejak audit rantai penuh ada; jurnal 'lab_order' TIDAK ada.
        foreach (['pr.auto_create', 'lab.assign', 'lab.qc_record', 'lab.complete'] as $action) {
            $this->assertTrue(
                AuditLog::where('action', $action)->exists(),
                "Audit {$action} harus tercatat",
            );
        }
        $this->assertSame(0, Journal::where('source_type', 'lab_order')->count());
        $this->assertSame($technician->id, (int) QcCheck::where('lens_process_id', $process->id)
            ->firstOrFail()->checked_by);
    }
}
