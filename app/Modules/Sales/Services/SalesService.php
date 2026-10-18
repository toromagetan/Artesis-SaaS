<?php

namespace App\Modules\Sales\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Clinical\Models\Prescription;
use App\Modules\Clinical\Services\PrescriptionService;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\BranchContext;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Master\Models\Setting;
use App\Modules\Membership\Services\PointsService;
use App\Modules\Platform\Services\AuditLogger;
use App\Modules\Purchasing\Services\PurchaseRequestService;
use App\Modules\Sales\Events\SaleCompleted;
use App\Modules\Sales\Events\SalePaymentRecorded;
use App\Modules\Sales\Events\SaleVoided;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SalePayment;
use App\Support\Money;
use App\Support\Numbering;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Layanan Penjualan (Blueprint D.6).
 *
 * - createSale: SATU transaksi DB; validasi total dengan Money
 *   (Σ baris − diskon + pajak eksklusif = total; Σ bayar awal ≤ total);
 *   snapshot resep & harga; resep ditandai terpakai; event SaleCompleted
 *   (stok keluar menyusul lewat listener Inventory di M.4).
 * - Diskon (item + transaksi) di atas ambang settings
 *   (sales.discount_threshold_percent, bawaan 20%) wajib izin
 *   sales.discount.high.
 * - Pembayaran append-only; koreksi = baris pembalik negatif.
 * - voidSale: alasan wajib; pembayaran dibalik baris-per-baris;
 *   ditolak bila proses lensa transaksi sudah done (harus lewat retur).
 */
class SalesService
{
    /**
     * @param  array<string, mixed>  $data  customer_id, prescription_id?, sale_date,
     *                                      discount_amount?, tax_mode?, doctor_id?, insurance_id?, notes?
     * @param  array<int, array<string, mixed>>  $items  product_id, qty, unit_price?, discount_amount?, prescription_ref?
     * @param  array<int, array<string, mixed>>  $payments  method, amount, reference?
     */
    public function createSale(User $actor, array $data, array $items, array $payments = []): Sale
    {
        if ($items === []) {
            throw ValidationException::withMessages(['items' => __('ui.sales.items_required')]);
        }

        return DB::transaction(function () use ($actor, $data, $items, $payments) {
            $customer = Customer::findOrFail($data['customer_id']);
            // Cabang transaksi (tempat stok keluar & pendapatan
            // tercatat) — BUKAN cabang asal pelanggan (ADR-012:
            // pelanggan global). Prioritas: (1) branch_id eksplisit
            // yang aktor berhak atasnya; (2) cabang aktif aktor;
            // (3) cabang asal pelanggan sebagai cadangan terakhir.
            $context = BranchContext::for($actor);
            if (! empty($data['branch_id'])) {
                $context->assertCanAccess((int) $data['branch_id']);
                $branchId = (int) $data['branch_id'];
            } else {
                $branchId = $context->currentBranchId() ?? $customer->branch_id;
            }

            $prescription = null;
            if (! empty($data['prescription_id'])) {
                $prescription = Prescription::findOrFail($data['prescription_id']);
                if ($prescription->customer_id !== $customer->id) {
                    throw ValidationException::withMessages([
                        'prescription_id' => __('ui.sales.prescription_mismatch'),
                    ]);
                }
            }

            // --- Hitung baris & total dengan Money (sen integer) ---
            $lines = [];
            $subtotal = Money::zero();
            foreach ($items as $i => $item) {
                $product = Product::findOrFail($item['product_id']);
                if (! $product->is_active) {
                    throw ValidationException::withMessages([
                        "items.{$i}.product_id" => __('ui.sales.product_inactive'),
                    ]);
                }
                $qty = (int) $item['qty'];
                if ($qty < 1) {
                    throw ValidationException::withMessages(["items.{$i}.qty" => __('ui.sales.qty_invalid')]);
                }
                $unitPrice = Money::fromDecimal((string) ($item['unit_price'] ?? $product->sale_price));
                $itemDiscount = Money::fromDecimal((string) ($item['discount_amount'] ?? '0'));
                $gross = $unitPrice->mul($qty);
                if ($itemDiscount->cmp($gross) > 0) {
                    throw ValidationException::withMessages([
                        "items.{$i}.discount_amount" => __('ui.sales.discount_exceeds_line'),
                    ]);
                }
                if ($product->type === 'rx' && empty($item['prescription_ref'])) {
                    throw ValidationException::withMessages([
                        "items.{$i}.prescription_ref" => __('ui.sales.rx_ref_required'),
                    ]);
                }
                $lineTotal = $gross->sub($itemDiscount);
                $subtotal = $subtotal->add($lineTotal);
                $lines[] = [
                    'product' => $product,
                    'qty' => $qty,
                    'unit_price' => $unitPrice,
                    'discount' => $itemDiscount,
                    'line_total' => $lineTotal,
                    'prescription_ref' => $item['prescription_ref'] ?? null,
                ];
            }

            $discount = Money::fromDecimal((string) ($data['discount_amount'] ?? '0'));
            if ($discount->cmp($subtotal) > 0) {
                throw ValidationException::withMessages(['discount_amount' => __('ui.sales.discount_exceeds_subtotal')]);
            }
            $this->assertDiscountPermission($actor, $subtotal, $discount, $lines);

            $taxMode = $data['tax_mode'] ?? (string) Setting::value('tax.mode', 'inclusive');
            $rate = (string) Setting::value('tax.rate_default', '11');
            $taxableBase = $subtotal->sub($discount);
            if ($taxMode === 'exclusive') {
                $tax = $taxableBase->percent($rate);
                $total = $taxableBase->add($tax);
            } else {
                // Inklusif: pajak sudah di dalam harga; ekstrak bagiannya.
                $tax = $taxableBase->inclusiveTaxShare($rate);
                $total = $taxableBase;
            }

            $sale = new Sale([
                'number' => Numbering::next('INV', now()),
                'customer_id' => $customer->id,
                'prescription_id' => $prescription?->id,
                'prescription_snapshot' => $prescription !== null ? $this->snapshotOf($prescription) : null,
                'cashier_id' => $actor->id,
                'doctor_id' => $data['doctor_id'] ?? null,
                'insurance_id' => $data['insurance_id'] ?? null,
                'sale_date' => $data['sale_date'] ?? now()->toDateString(),
                'subtotal_amount' => $subtotal->toDecimalString(),
                'discount_amount' => $discount->toDecimalString(),
                'tax_amount' => $tax->toDecimalString(),
                'tax_mode' => $taxMode,
                'total_amount' => $total->toDecimalString(),
                'paid_amount' => '0.00',
                'status' => 'active',
                'notes' => $data['notes'] ?? null,
            ]);
            $sale->branch_id = $branchId;
            $sale->save();

            foreach ($lines as $line) {
                $sale->items()->create([
                    'product_id' => $line['product']->id,
                    'product_name_snapshot' => $line['product']->name,
                    'type' => $line['product']->type,
                    'qty' => $line['qty'],
                    'unit_price' => $line['unit_price']->toDecimalString(),
                    'discount_amount' => $line['discount']->toDecimalString(),
                    'line_total' => $line['line_total']->toDecimalString(),
                    'prescription_ref' => $line['prescription_ref'],
                ]);
            }

            $paid = Money::zero();
            foreach ($payments as $payment) {
                $amount = Money::fromDecimal((string) $payment['amount']);
                if (! $amount->isPositive()) {
                    throw ValidationException::withMessages(['payments' => __('ui.sales.payment_invalid')]);
                }
                $paid = $paid->add($amount);
            }
            if ($paid->cmp($total) > 0) {
                throw ValidationException::withMessages(['payments' => __('ui.sales.payment_exceeds_total')]);
            }

            // Pembayaran metode 'points' (F3.6): nominal wajib kelipatan
            // nilai tukar dan saldo poin pelanggan (dikunci) mencukupi.
            $pointsService = app(PointsService::class);
            $pointsPerRow = [];
            $pointsTotal = 0;
            foreach ($payments as $i => $payment) {
                if ($payment['method'] === 'points') {
                    $pointsPerRow[$i] = $pointsService->pointsForAmount(Money::fromDecimal((string) $payment['amount']));
                    $pointsTotal += $pointsPerRow[$i];
                }
            }
            if ($pointsTotal > 0) {
                $pointsService->assertRedeemable($customer, $pointsTotal);
            }

            foreach ($payments as $i => $payment) {
                $paymentRow = $sale->payments()->create([
                    'method' => $payment['method'],
                    'amount' => Money::fromDecimal((string) $payment['amount'])->toDecimalString(),
                    'paid_at' => now(),
                    'reference' => $payment['reference'] ?? null,
                    'received_by' => $actor->id,
                ]);
                if (isset($pointsPerRow[$i])) {
                    $pointsService->recordRedemption($customer, $paymentRow, $pointsPerRow[$i]);
                }
            }
            if (! $paid->isZero()) {
                $sale->update(['paid_amount' => $paid->toDecimalString()]);
            }

            // Jadwal cicilan atas SISA piutang (F3.2): DP menjadi piutang
            // terjadwal. Dibuat di transaksi yang sama; gagal = gulung semua.
            if (! empty($data['installment_count'])) {
                app(InstallmentService::class)->createSchedule(
                    $actor,
                    $sale,
                    (int) $data['installment_count'],
                    (string) ($data['installment_first_due'] ?? now()->addDays(30)->toDateString()),
                    (int) ($data['installment_interval_days'] ?? 30),
                );
            }

            if ($prescription !== null) {
                app(PrescriptionService::class)->markUsed($prescription);
            }

            app(AuditLogger::class)->log(
                action: 'sales.create',
                subjectType: 'sale',
                subjectId: $sale->id,
                newValues: ['number' => $sale->number, 'total' => $total->toDecimalString(), 'customer_id' => $customer->id],
                branchId: $branchId,
                actor: $actor,
            );

            // PR OTOMATIS kekurangan lensa (paritas Artesis): lensa RX
            // selalu diproses melalui PR; lensa stok & softlens dibuatkan
            // PR hanya untuk qty yang tidak tersedia di cabang transaksi.
            // Saldo dibaca SEBELUM gerakan keluar penjualan dicatat
            // listener SaleCompleted. Masih dalam transaksi yang sama —
            // penjualan gagal, PR ikut gulung.
            $shortages = [];
            $available = [];
            foreach ($lines as $line) {
                $type = $line['product']->type;
                if (! in_array($type, ['lens', 'rx', 'softlens'], true)) {
                    continue;
                }
                if ($type === 'rx') {
                    $shortages[$line['product']->id] = ($shortages[$line['product']->id] ?? 0) + $line['qty'];

                    continue;
                }
                $pid = $line['product']->id;
                $available[$pid] ??= app(InventoryService::class)->currentStock($pid, $branchId);
                $covered = min($line['qty'], max(0, $available[$pid]));
                $available[$pid] -= $covered;
                if ($line['qty'] - $covered > 0) {
                    $shortages[$pid] = ($shortages[$pid] ?? 0) + ($line['qty'] - $covered);
                }
            }
            if ($shortages !== []) {
                $prItems = [];
                foreach ($shortages as $productId => $qty) {
                    $prItems[] = ['product_id' => $productId, 'qty' => $qty];
                }
                app(PurchaseRequestService::class)
                    ->createAutomatic($actor, $sale, $prItems);
            }

            SaleCompleted::dispatch($sale);

            return $sale;
        });
    }

    public function addPayment(User $actor, Sale $sale, string $method, string $amount, ?string $reference = null): SalePayment
    {
        return DB::transaction(function () use ($actor, $sale, $method, $amount, $reference) {
            $sale = Sale::lockForUpdate()->findOrFail($sale->id);
            if ($sale->isVoid()) {
                throw ValidationException::withMessages(['amount' => __('ui.sales.sale_void')]);
            }
            $value = Money::fromDecimal($amount);
            if (! $value->isPositive()) {
                throw ValidationException::withMessages(['amount' => __('ui.sales.payment_invalid')]);
            }
            if ($value->cmp($sale->remaining()) > 0) {
                throw ValidationException::withMessages(['amount' => __('ui.sales.payment_exceeds_total')]);
            }

            // Pembayaran metode 'points' (F3.6): kelipatan nilai tukar +
            // saldo poin mencukupi (baris pelanggan dikunci di layanan).
            $pointsNeeded = null;
            if ($method === 'points') {
                $pointsService = app(PointsService::class);
                $pointsNeeded = $pointsService->pointsForAmount($value);
                $pointsService->assertRedeemable($sale->customer, $pointsNeeded);
            }

            $payment = $sale->payments()->create([
                'method' => $method,
                'amount' => $value->toDecimalString(),
                'paid_at' => now(),
                'reference' => $reference,
                'received_by' => $actor->id,
            ]);
            if ($pointsNeeded !== null) {
                app(PointsService::class)
                    ->recordRedemption($sale->customer, $payment, $pointsNeeded);
            }
            $sale->update([
                'paid_amount' => Money::fromDecimal((string) $sale->paid_amount)->add($value)->toDecimalString(),
            ]);

            // Penerimaan cicilan mengalir ke baris jadwal FIFO (F3.2).
            app(InstallmentService::class)->allocate($sale, $value);

            app(AuditLogger::class)->log(
                action: 'sales.payment',
                subjectType: 'sale',
                subjectId: $sale->id,
                newValues: ['payment_id' => $payment->id, 'amount' => $value->toDecimalString(), 'method' => $method],
                branchId: $sale->branch_id,
                actor: $actor,
            );

            SalePaymentRecorded::dispatch($payment, $sale);

            return $payment;
        });
    }

    public function voidSale(User $actor, Sale $sale, string $reason): Sale
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => __('ui.sales.void_reason_required')]);
        }

        return DB::transaction(function () use ($actor, $sale, $reason) {
            $sale = Sale::lockForUpdate()->findOrFail($sale->id);
            if ($sale->isVoid()) {
                throw ValidationException::withMessages(['reason' => __('ui.sales.already_void')]);
            }

            // Proses lensa yang sudah done menutup jalan void (D.6).
            if (Schema::hasTable('lens_processes')) {
                $doneProcess = DB::table('lens_processes')
                    ->where('sale_id', $sale->id)
                    ->where('status', 'done')
                    ->exists();
                if ($doneProcess) {
                    throw ValidationException::withMessages(['reason' => __('ui.sales.void_blocked_lab_done')]);
                }
            }

            // Balik setiap pembayaran asli dengan baris negatif.
            foreach ($sale->payments()->whereNull('reversal_of_id')->get() as $payment) {
                $sale->payments()->create([
                    'method' => $payment->method,
                    'amount' => Money::fromDecimal((string) $payment->amount)->negate()->toDecimalString(),
                    'paid_at' => now(),
                    'reference' => 'VOID '.$sale->number,
                    'received_by' => $actor->id,
                    'reversal_of_id' => $payment->id,
                ]);
            }

            $sale->update([
                'status' => 'void',
                'void_reason' => $reason,
                'voided_by' => $actor->id,
                'voided_at' => now(),
                'paid_amount' => '0.00',
            ]);

            app(AuditLogger::class)->log(
                action: 'sales.void',
                subjectType: 'sale',
                subjectId: $sale->id,
                newValues: ['reason' => $reason],
                branchId: $sale->branch_id,
                actor: $actor,
            );

            // PR otomatis yang belum diproses ikut batal (paritas Artesis).
            app(PurchaseRequestService::class)
                ->cancelAutomaticForSale($actor, $sale);

            // Jadwal cicilan adalah rencana atas piutang — ikut batal.
            app(InstallmentService::class)->removeForVoidedSale($sale);

            SaleVoided::dispatch($sale);

            return $sale;
        });
    }

    /** @param array<int, array<string, mixed>> $lines */
    private function assertDiscountPermission(User $actor, Money $subtotal, Money $saleDiscount, array $lines): void
    {
        if ($subtotal->isZero()) {
            return;
        }
        $totalDiscount = $saleDiscount;
        foreach ($lines as $line) {
            $totalDiscount = $totalDiscount->add($line['discount']);
        }
        if ($totalDiscount->isZero()) {
            return;
        }

        $threshold = (string) Setting::value('sales.discount_threshold_percent', '20');
        // Ambang dalam basis-point (persen × 100) diurai manual dari string.
        [$tWhole, $tFraction] = array_pad(explode('.', str_replace(',', '.', $threshold), 2), 2, '');
        $thresholdBp = (int) $tWhole * 100 + (int) str_pad(substr($tFraction, 0, 2), 2, '0');
        // totalDiscount/subtotal > threshold%  ⇔  totalDiscount×10000 > subtotal×thresholdBp
        $exceeds = $totalDiscount->cents() * 10000 > $subtotal->cents() * $thresholdBp;
        if ($exceeds && ! $actor->hasPermission('sales.discount.high')) {
            throw ValidationException::withMessages([
                'discount_amount' => __('ui.sales.discount_high_forbidden', ['percent' => $threshold]),
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function snapshotOf(Prescription $prescription): array
    {
        return [
            'prescription_id' => $prescription->id,
            'examined_at' => $prescription->examined_at->format('Y-m-d'),
            'examiner_id' => $prescription->examiner_id,
            'od' => $prescription->eye('od'),
            'os' => $prescription->eye('os'),
            'pd_total' => $prescription->pd_total !== null ? (string) $prescription->pd_total : null,
            'prism' => $prescription->prism,
            'notes' => $prescription->notes,
        ];
    }
}
