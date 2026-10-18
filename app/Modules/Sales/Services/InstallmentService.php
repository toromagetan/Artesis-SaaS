<?php

namespace App\Modules\Sales\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Platform\Services\AuditLogger;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleInstallment;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Jadwal cicilan & umur piutang pelanggan (F3.2, PRD 2.4).
 *
 * - createSchedule: sisa piutang penjualan dibagi rata per sen integer;
 *   selisih pembulatan masuk cicilan TERAKHIR (Σ cicilan = sisa persis).
 *   Jadwal hanya untuk penjualan aktif bersisa > 0 dan belum berjadwal.
 * - allocate: pembayaran (dari SalesService::addPayment) dialokasikan
 *   FIFO per sequence di bawah lockForUpdate — Σ paid_amount baris
 *   tidak pernah melampaui Σ pembayaran non-jadwal (DP tidak termasuk:
 *   jadwal memang dibuat atas SISA sesudah DP).
 * - Umur piutang: tanggal acuan = due_date cicilan belum lunas paling
 *   awal; tanpa jadwal, acuan = tanggal penjualan. Kategori:
 *   ≤30 'current' (lancar), 31–60 'd30', 61–90 'd60', >90 'd90'.
 */
class InstallmentService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @return Collection<int, SaleInstallment>
     */
    public function createSchedule(User $actor, Sale $sale, int $count, string $firstDueDate, int $intervalDays = 30): Collection
    {
        if ($count < 1 || $count > 36) {
            throw ValidationException::withMessages(['installment_count' => __('ui.sales.installment_invalid_count')]);
        }
        if ($intervalDays < 1 || $intervalDays > 365) {
            throw ValidationException::withMessages(['installment_interval_days' => __('ui.sales.installment_invalid_interval')]);
        }

        return DB::transaction(function () use ($actor, $sale, $count, $firstDueDate, $intervalDays) {
            $sale = Sale::lockForUpdate()->findOrFail($sale->id);
            if ($sale->isVoid()) {
                throw ValidationException::withMessages(['installment_count' => __('ui.sales.sale_void')]);
            }
            if ($sale->installments()->exists()) {
                throw ValidationException::withMessages(['installment_count' => __('ui.sales.installment_exists')]);
            }
            $remaining = $sale->remaining();
            if (! $remaining->isPositive()) {
                throw ValidationException::withMessages(['installment_count' => __('ui.sales.installment_no_remaining')]);
            }

            $totalCents = $remaining->cents();
            $base = intdiv($totalCents, $count);
            $firstDue = Carbon::parse($firstDueDate)->startOfDay();

            $rows = new Collection;
            for ($i = 0; $i < $count; $i++) {
                $cents = $i === $count - 1 ? $totalCents - ($base * ($count - 1)) : $base;
                $rows->push($sale->installments()->create([
                    'sequence' => $i + 1,
                    'due_date' => $firstDue->copy()->addDays($intervalDays * $i)->toDateString(),
                    'amount' => Money::of($cents)->toDecimalString(),
                    'paid_amount' => '0.00',
                    'status' => 'open',
                ]));
            }

            $this->audit->log(
                action: 'sales.installment_schedule',
                subjectType: 'sale',
                subjectId: $sale->id,
                newValues: ['count' => $count, 'total' => $remaining->toDecimalString(), 'first_due_date' => $firstDue->toDateString(), 'interval_days' => $intervalDays],
                branchId: $sale->branch_id,
                actor: $actor,
            );

            return $rows;
        });
    }

    /** Alokasikan satu pembayaran ke baris jadwal, FIFO per sequence. */
    public function allocate(Sale $sale, Money $amount): void
    {
        if (! $amount->isPositive()) {
            return;
        }
        $left = $amount;
        $rows = $sale->installments()->where('status', '!=', 'paid')
            ->orderBy('sequence')->lockForUpdate()->get();
        foreach ($rows as $row) {
            if (! $left->isPositive()) {
                break;
            }
            $room = $row->remaining();
            if (! $room->isPositive()) {
                continue;
            }
            $applied = $left->min($room);
            $paid = Money::fromDecimal((string) $row->paid_amount)->add($applied);
            $row->update([
                'paid_amount' => $paid->toDecimalString(),
                'status' => $paid->cmp(Money::fromDecimal((string) $row->amount)) >= 0 ? 'paid' : 'partial',
            ]);
            $left = $left->sub($applied);
        }
    }

    /** Jadwal ikut batal saat penjualan di-void (sejajar proses lensa & PR otomatis). */
    public function removeForVoidedSale(Sale $sale): void
    {
        $sale->installments()->delete();
    }

    /** Tanggal acuan umur: due_date belum lunas paling awal, else tanggal penjualan. */
    public function referenceDate(Sale $sale): Carbon
    {
        $due = $sale->installments()->where('status', '!=', 'paid')
            ->orderBy('due_date')->value('due_date');

        return $due !== null
            ? Carbon::parse($due)->startOfDay()
            : Carbon::parse($sale->sale_date)->startOfDay();
    }

    /**
     * @return array{reference_date: Carbon, age_days: int, bucket: string}
     */
    public function agingFor(Sale $sale, ?Carbon $today = null): array
    {
        $today ??= today();
        $reference = $this->referenceDate($sale);
        $age = max(0, (int) $reference->diffInDays($today, false));
        $bucket = match (true) {
            $age > 90 => 'd90',
            $age > 60 => 'd60',
            $age > 30 => 'd30',
            default => 'current',
        };

        return ['reference_date' => $reference, 'age_days' => $age, 'bucket' => $bucket];
    }
}
