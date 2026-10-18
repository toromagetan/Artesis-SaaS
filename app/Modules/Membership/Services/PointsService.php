<?php

namespace App\Modules\Membership\Services;

use App\Modules\Accounting\Models\Journal;
use App\Modules\Accounting\Services\PostingService;
use App\Modules\Accounting\Support\Chart;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Setting;
use App\Modules\Membership\Models\MembershipLevel;
use App\Modules\Membership\Models\PointsLedger;
use App\Modules\Platform\Services\AuditLogger;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SalePayment;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Mesin poin membership (F3.6, Blueprint D.8).
 *
 * Aturan uang poin (nilai dari settings, dapat diatur Admin):
 * - points.earn_amount   rupiah belanja (di luar pajak) per 1 poin (bawaan 10000)
 * - points.redeem_value  nilai rupiah 1 poin saat ditukar (bawaan 100)
 * - points.expiry_months masa berlaku poin earn (bawaan 12)
 *
 * Jurnal liabilitas (akun 2-1200 Liabilitas Poin):
 * - earn/adjust+   : Dr Beban Program Loyalitas (5-1300) / Cr Liabilitas
 * - redeem         : lewat jurnal pembayaran penjualan yang sudah ada
 *                    (Dr Liabilitas via akun tender 'points' / Cr Piutang)
 * - expire/adjust- : Dr Liabilitas / Cr Beban Program Loyalitas
 * - void penjualan : jurnal earn dibalik (reverse), redeem dipulihkan
 *                    sebagai baris reversal (jurnalnya dibalik handler
 *                    pembayaran di Accounting).
 * Invariant gerbang: saldo 2-1200 == Σ poin aktif x nilai tukar.
 *
 * Kedaluwarsa dihitung per LOT earn dengan konsumsi FIFO; baris
 * reversal positif memulihkan lot yang terakhir terkonsumsi lebih
 * dulu. Perhitungan ulang riwayat bersifat deterministik dari
 * points_ledger saja.
 */
class PointsService
{
    public function __construct(
        private readonly PostingService $posting,
        private readonly AuditLogger $audit,
    ) {}

    public function earnAmountRupiah(): int
    {
        return max(1, (int) Setting::value('points.earn_amount', '10000'));
    }

    public function pointValue(): Money
    {
        return Money::fromDecimal((string) Setting::value('points.redeem_value', '100'));
    }

    public function expiryMonths(): int
    {
        return max(0, (int) Setting::value('points.expiry_months', '12'));
    }

    public function balance(Customer $customer): int
    {
        return (int) PointsLedger::where('customer_id', $customer->id)->sum('points');
    }

    /** Level berjalan: level tersimpan, atau level aktif terendah. */
    public function currentLevel(Customer $customer): ?MembershipLevel
    {
        if ($customer->membership_level_id !== null) {
            $level = MembershipLevel::find($customer->membership_level_id);
            if ($level !== null) {
                return $level;
            }
        }

        return MembershipLevel::where('is_active', true)
            ->orderBy('min_accumulation')->orderBy('id')->first();
    }

    /** Pengali level dalam perseratus (hindari float): '1.25' -> 125. */
    public function multiplierHundredths(Customer $customer): int
    {
        $raw = (string) ($this->currentLevel($customer)?->point_multiplier ?? '1');
        [$whole, $frac] = array_pad(explode('.', $raw, 2), 2, '0');

        return ((int) $whole * 100) + (int) str_pad(substr($frac, 0, 2), 2, '0');
    }

    /** Akumulasi belanja bersih: Σ penjualan aktif − Σ retur pelanggan. */
    public function accumulation(Customer $customer): Money
    {
        $sales = (string) Sale::where('customer_id', $customer->id)
            ->where('status', 'active')->sum('total_amount');
        $returns = (string) DB::table('sale_returns')
            ->join('sales', 'sales.id', '=', 'sale_returns.sale_id')
            ->where('sales.customer_id', $customer->id)
            ->where('sales.status', 'active')
            ->sum('sale_returns.total_amount');

        return Money::fromDecimal($sales)->sub(Money::fromDecimal($returns));
    }

    /** Naik/turun level mengikuti akumulasi belanja bersih terkini. */
    public function evaluateLevel(Customer $customer): void
    {
        $accumulation = $this->accumulation($customer);
        $level = MembershipLevel::where('is_active', true)
            ->where('min_accumulation', '<=', $accumulation->toDecimalString())
            ->orderByDesc('min_accumulation')->orderByDesc('id')
            ->first();
        if ($level !== null && (int) $customer->membership_level_id !== (int) $level->id) {
            $customer->update(['membership_level_id' => $level->id]);
        }
    }

    /**
     * Earn dari penjualan selesai (idempoten per penjualan).
     * Basis = total di luar pajak; poin = floor(basis / tarif) x pengali level.
     */
    public function earnForSale(Sale $sale): ?PointsLedger
    {
        $customer = $sale->customer;
        if ($customer === null || $sale->isVoid()) {
            return null;
        }
        $existing = PointsLedger::where('type', 'earn')
            ->where('reference_type', 'sale')->where('reference_id', $sale->id)->first();
        if ($existing !== null) {
            return $existing;
        }

        $basis = Money::fromDecimal((string) $sale->total_amount)
            ->sub(Money::fromDecimal((string) $sale->tax_amount));
        if (! $basis->isPositive()) {
            return null;
        }
        $basePoints = intdiv($basis->cents(), $this->earnAmountRupiah() * 100);
        $earned = intdiv($basePoints * $this->multiplierHundredths($customer), 100);
        if ($earned <= 0) {
            return null;
        }

        $row = PointsLedger::create([
            'customer_id' => $customer->id,
            'type' => 'earn',
            'points' => $earned,
            'reference_type' => 'sale',
            'reference_id' => $sale->id,
            'expires_at' => $this->expiryMonths() > 0
                ? Carbon::parse($sale->sale_date)->addMonths($this->expiryMonths())->toDateString()
                : null,
        ]);

        $value = $this->pointValue()->mul($earned);
        if ($value->isPositive()) {
            $this->posting->postOnce('points_earn', $row->id, $sale->sale_date, [
                ['account' => Chart::BEBAN_LOYALITAS, 'branch_id' => $sale->branch_id, 'debit' => $value->toDecimalString(), 'memo' => $sale->number],
                ['account' => Chart::LIABILITAS_POIN, 'branch_id' => $sale->branch_id, 'credit' => $value->toDecimalString(), 'memo' => $sale->number],
            ], 'Poin earn '.$sale->number, $sale->cashier);
        }

        $this->evaluateLevel($customer);

        return $row;
    }

    /**
     * Jumlah poin untuk sebuah nominal pembayaran metode 'points'.
     * Nominal WAJIB kelipatan persis nilai tukar.
     */
    public function pointsForAmount(Money $amount): int
    {
        $valueCents = $this->pointValue()->cents();
        if ($valueCents <= 0 || $amount->cents() % $valueCents !== 0) {
            throw ValidationException::withMessages(['amount' => __('ui.members.points_not_multiple')]);
        }

        return intdiv($amount->cents(), $valueCents);
    }

    /**
     * Pastikan saldo mencukupi. Mengunci baris pelanggan agar dua
     * penukaran bersamaan untuk pelanggan yang sama terserialisasi —
     * WAJIB dipanggil di dalam transaksi (konteks pembayaran).
     */
    public function assertRedeemable(Customer $customer, int $points): void
    {
        Customer::lockForUpdate()->findOrFail($customer->id);
        if ($this->balance($customer) < $points) {
            throw ValidationException::withMessages(['amount' => __('ui.members.points_insufficient')]);
        }
    }

    /** Catat penukaran untuk satu baris pembayaran (jurnal via handler pembayaran). */
    public function recordRedemption(Customer $customer, SalePayment $payment, int $points): PointsLedger
    {
        return PointsLedger::create([
            'customer_id' => $customer->id,
            'type' => 'redeem',
            'points' => -$points,
            'reference_type' => 'sale_payment',
            'reference_id' => $payment->id,
            'expires_at' => null,
        ]);
    }

    /** Pembalikan penuh poin sebuah penjualan yang di-void. */
    public function reverseForVoidedSale(Sale $sale): void
    {
        $customer = $sale->customer;
        if ($customer === null) {
            return;
        }

        // Earn: baris reversal + jurnal earn dibalik.
        $earn = PointsLedger::where('type', 'earn')
            ->where('reference_type', 'sale')->where('reference_id', $sale->id)->first();
        if ($earn !== null && ! $this->hasReversalFor($earn)) {
            PointsLedger::create([
                'customer_id' => $customer->id,
                'type' => 'reversal',
                'points' => -$earn->points,
                'reference_type' => 'points_ledger',
                'reference_id' => $earn->id,
                'expires_at' => null,
            ]);
            $journal = Journal::where('source_type', 'points_earn')->where('source_id', $earn->id)->first();
            if ($journal !== null) {
                $actor = $sale->voided_by !== null ? User::find($sale->voided_by) : null;
                $this->posting->reverse($journal, 'Void '.$sale->number, $actor);
            }
        }

        // Redeem pada penjualan ini: pulihkan poinnya (jurnal pembayaran
        // sudah dibalik handler Accounting atas SaleVoided).
        $paymentIds = $sale->payments()->pluck('id');
        $redeems = PointsLedger::where('type', 'redeem')
            ->where('reference_type', 'sale_payment')->whereIn('reference_id', $paymentIds)->get();
        foreach ($redeems as $redeem) {
            if ($this->hasReversalFor($redeem)) {
                continue;
            }
            PointsLedger::create([
                'customer_id' => $customer->id,
                'type' => 'reversal',
                'points' => -$redeem->points,
                'reference_type' => 'points_ledger',
                'reference_id' => $redeem->id,
                'expires_at' => null,
            ]);
        }

        $this->evaluateLevel($customer);
    }

    private function hasReversalFor(PointsLedger $row): bool
    {
        return PointsLedger::where('type', 'reversal')
            ->where('reference_type', 'points_ledger')->where('reference_id', $row->id)
            ->exists();
    }

    /**
     * Kedaluwarsa: expire sisa belum-terpakai dari lot earn yang masa
     * berlakunya sudah lewat. Mengembalikan jumlah baris expire dibuat.
     */
    public function expireRun(?Carbon $today = null): int
    {
        $today ??= today();
        $customerIds = PointsLedger::where('type', 'earn')
            ->whereNotNull('expires_at')->where('expires_at', '<', $today->toDateString())
            ->distinct()->pluck('customer_id');

        $created = 0;
        foreach ($customerIds as $customerId) {
            $customer = Customer::find($customerId);
            if ($customer === null) {
                continue;
            }
            foreach ($this->unspentLots($customer) as $lot) {
                if ($lot['expires_at'] === null || $lot['expires_at'] >= $today->toDateString() || $lot['remaining'] <= 0) {
                    continue;
                }
                DB::transaction(function () use ($customer, $lot, $today, &$created) {
                    $row = PointsLedger::create([
                        'customer_id' => $customer->id,
                        'type' => 'expire',
                        'points' => -$lot['remaining'],
                        'reference_type' => 'points_ledger',
                        'reference_id' => $lot['id'],
                        'expires_at' => null,
                    ]);
                    $value = $this->pointValue()->mul($lot['remaining']);
                    if ($value->isPositive()) {
                        $this->posting->postOnce('points_expire', $row->id, $today->toDateString(), [
                            ['account' => Chart::LIABILITAS_POIN, 'branch_id' => $customer->branch_id, 'debit' => $value->toDecimalString(), 'memo' => 'expire'],
                            ['account' => Chart::BEBAN_LOYALITAS, 'branch_id' => $customer->branch_id, 'credit' => $value->toDecimalString(), 'memo' => 'expire'],
                        ], 'Poin kedaluwarsa', null);
                    }
                    $created++;
                });
            }
        }

        return $created;
    }

    /**
     * Lot earn beserta sisa belum-terpakainya, hasil putar-ulang
     * riwayat: konsumsi negatif berjalan FIFO; reversal positif
     * memulihkan lot yang paling akhir terkonsumsi lebih dulu.
     *
     * @return array<int, array{id: int, remaining: int, expires_at: ?string}>
     */
    public function unspentLots(Customer $customer): array
    {
        $rows = PointsLedger::where('customer_id', $customer->id)
            ->orderBy('created_at')->orderBy('id')->get();

        $lots = [];
        foreach ($rows as $row) {
            if ($row->type === 'earn') {
                $lots[] = [
                    'id' => $row->id,
                    'original' => $row->points,
                    'remaining' => $row->points,
                    'expires_at' => $row->expires_at?->toDateString(),
                ];

                continue;
            }
            if ($row->points < 0) {
                $need = -$row->points;
                foreach ($lots as &$lot) {
                    if ($need <= 0) {
                        break;
                    }
                    $take = min($lot['remaining'], $need);
                    $lot['remaining'] -= $take;
                    $need -= $take;
                }
                unset($lot);

                continue;
            }
            if ($row->type === 'reversal' && $row->points > 0) {
                $restore = $row->points;
                for ($i = count($lots) - 1; $i >= 0 && $restore > 0; $i--) {
                    $room = $lots[$i]['original'] - $lots[$i]['remaining'];
                    $add = min($room, $restore);
                    $lots[$i]['remaining'] += $add;
                    $restore -= $add;
                }
            }
        }

        return $lots;
    }

    /**
     * Penyesuaian manual ber-alasan (izin points.adjust ditegakkan di
     * rute). Positif = earn manual (ikut masa berlaku); negatif dibatasi
     * saldo. Keduanya terjurnal ke liabilitas.
     */
    public function adjust(User $actor, Customer $customer, int $points, string $reason): PointsLedger
    {
        $reason = trim($reason);
        if ($points === 0) {
            throw ValidationException::withMessages(['points' => __('ui.members.adjust_zero')]);
        }
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => __('ui.members.adjust_reason_required')]);
        }

        return DB::transaction(function () use ($actor, $customer, $points, $reason) {
            if ($points < 0) {
                Customer::lockForUpdate()->findOrFail($customer->id);
                if ($this->balance($customer) + $points < 0) {
                    throw ValidationException::withMessages(['points' => __('ui.members.points_insufficient')]);
                }
            }

            $row = PointsLedger::create([
                'customer_id' => $customer->id,
                'type' => $points > 0 ? 'earn' : 'redeem',
                'points' => $points,
                'reference_type' => 'manual_adjustment',
                'reference_id' => null,
                'expires_at' => $points > 0 && $this->expiryMonths() > 0
                    ? today()->addMonths($this->expiryMonths())->toDateString()
                    : null,
            ]);

            $value = $this->pointValue()->mul(abs($points));
            if ($value->isPositive()) {
                $lines = $points > 0
                    ? [
                        ['account' => Chart::BEBAN_LOYALITAS, 'branch_id' => $customer->branch_id, 'debit' => $value->toDecimalString(), 'memo' => 'adjust'],
                        ['account' => Chart::LIABILITAS_POIN, 'branch_id' => $customer->branch_id, 'credit' => $value->toDecimalString(), 'memo' => 'adjust'],
                    ]
                    : [
                        ['account' => Chart::LIABILITAS_POIN, 'branch_id' => $customer->branch_id, 'debit' => $value->toDecimalString(), 'memo' => 'adjust'],
                        ['account' => Chart::BEBAN_LOYALITAS, 'branch_id' => $customer->branch_id, 'credit' => $value->toDecimalString(), 'memo' => 'adjust'],
                    ];
                $this->posting->postOnce('points_adjust', $row->id, today()->toDateString(), $lines, 'Penyesuaian poin manual', $actor);
            }

            $this->audit->log(
                action: 'points.adjust',
                subjectType: 'customer',
                subjectId: $customer->id,
                newValues: ['points' => $points, 'reason' => $reason, 'ledger_id' => $row->id],
                branchId: $customer->branch_id,
                actor: $actor,
            );

            return $row;
        });
    }
}
