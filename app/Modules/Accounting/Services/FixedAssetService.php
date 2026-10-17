<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\FixedAsset;
use App\Modules\Accounting\Models\Journal;
use App\Modules\Accounting\Support\Chart;
use App\Modules\Identity\Models\User;
use App\Modules\Platform\Services\AuditLogger;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Aset tetap & penyusutan (F1.4, izin assets.manage).
 *
 * Penyusutan garis lurus: beban bulanan = harga perolehan / masa
 * manfaat (sen integer, pembulatan half-up). Bulan terjadwal
 * terakhir membebankan seluruh sisa agar akumulasi berakhir
 * PERSIS sebesar harga perolehan. Penyusutan dimulai bulan
 * perolehan. Setiap aset-periode = satu jurnal (Dr Beban
 * Penyusutan 5-1600 / Cr Akumulasi Penyusutan 1-1600) lewat
 * PostingService — periode terkunci menggulung transaksi aset
 * itu. Idempoten: jurnal periode yang sudah ada tidak digandakan
 * (diperiksa ulang di bawah lockForUpdate).
 */
class FixedAssetService
{
    public function __construct(
        private readonly PostingService $posting,
        private readonly AuditLogger $audit,
    ) {}

    /** @param array{name: string, acquired_at: string, acquisition_cost: string, useful_life_months: int} $data */
    public function create(User $actor, array $data): FixedAsset
    {
        $cost = Money::fromDecimal((string) $data['acquisition_cost']);
        if (! $cost->isPositive() || (int) $data['useful_life_months'] < 1) {
            throw ValidationException::withMessages(['acquisition_cost' => __('ui.assets.invalid_input')]);
        }

        $asset = FixedAsset::create([
            'name' => $data['name'],
            'acquired_at' => $data['acquired_at'],
            'acquisition_cost' => $cost->toDecimalString(),
            'useful_life_months' => (int) $data['useful_life_months'],
            'method' => 'straight_line',
            'created_by' => $actor->id,
        ])->refresh(); // muat nilai bawaan DB (status, accumulated_amount)

        $this->audit->log(action: 'assets.create', subjectType: 'fixed_asset', subjectId: $asset->id,
            newValues: ['name' => $asset->name, 'cost' => $cost->toDecimalString()], actor: $actor);

        return $asset;
    }

    /** Pelepasan aset (bukan hapus): status active → disposed. */
    public function dispose(User $actor, FixedAsset $asset): FixedAsset
    {
        if ($asset->status === 'disposed') {
            throw ValidationException::withMessages(['status' => __('ui.assets.already_disposed')]);
        }
        $asset->forceFill(['status' => 'disposed'])->save();

        $this->audit->log(action: 'assets.dispose', subjectType: 'fixed_asset', subjectId: $asset->id,
            oldValues: ['status' => 'active'], newValues: ['status' => 'disposed'], actor: $actor);

        return $asset;
    }

    /**
     * Jalankan penyusutan satu periode (YYYY-MM) untuk semua aset
     * yang memenuhi syarat. Mengembalikan ringkasan proses.
     *
     * @return array{posted: int, skipped: int, total: Money}
     */
    public function depreciatePeriod(?User $actor, string $period): array
    {
        $periodEnd = $this->periodEnd($period);
        $journalDate = $period === now()->format('Y-m') ? now()->toDateString() : $periodEnd;

        $posted = 0;
        $skipped = 0;
        $total = Money::zero();

        $assetIds = FixedAsset::where('status', 'active')->orderBy('id')->pluck('id');
        foreach ($assetIds as $assetId) {
            $charge = DB::transaction(function () use ($actor, $assetId, $period, $periodEnd, $journalDate) {
                $asset = FixedAsset::whereKey($assetId)->lockForUpdate()->first();
                if ($asset === null || $asset->status !== 'active') {
                    return null;
                }
                // Belum dimiliki pada periode ini / sudah habis nilai bukunya.
                if ($asset->acquired_at->format('Y-m-d') > $periodEnd || $asset->isFullyDepreciated()) {
                    return null;
                }
                // Idempotensi per aset-periode, diperiksa di bawah kunci.
                $already = Journal::where('source_type', 'depreciation')
                    ->where('source_id', $asset->id)
                    ->whereRaw('substr(journal_date, 1, 7) = ?', [$period])
                    ->exists();
                if ($already) {
                    return null;
                }

                $monthsPosted = Journal::where('source_type', 'depreciation')
                    ->where('source_id', $asset->id)
                    ->count();
                $remaining = $asset->bookValue();
                $monthly = $this->monthlyAmount($asset);
                // Bulan terjadwal terakhir: bebankan seluruh sisa.
                $charge = $monthsPosted + 1 >= (int) $asset->useful_life_months
                    ? $remaining
                    : $monthly->min($remaining);
                if (! $charge->isPositive()) {
                    return null;
                }

                $this->posting->post('depreciation', $asset->id, $journalDate, [
                    ['account' => Chart::BEBAN_PENYUSUTAN, 'debit' => $charge->toDecimalString()],
                    ['account' => Chart::AKUMULASI_PENYUSUTAN, 'credit' => $charge->toDecimalString()],
                ], __('ui.assets.depreciation_description', ['name' => $asset->name, 'period' => $period]), $actor);

                $asset->forceFill([
                    'accumulated_amount' => $asset->accumulated()->add($charge)->toDecimalString(),
                ])->save();

                return $charge;
            });

            if ($charge === null) {
                $skipped++;
            } else {
                $posted++;
                $total = $total->add($charge);
            }
        }

        return ['posted' => $posted, 'skipped' => $skipped, 'total' => $total];
    }

    /** Beban bulanan garis lurus: sen integer, half-up. */
    public function monthlyAmount(FixedAsset $asset): Money
    {
        $cents = $asset->cost()->cents();
        $months = max(1, (int) $asset->useful_life_months);
        $q = intdiv($cents, $months);
        if (($cents % $months) * 2 >= $months) {
            $q++;
        }

        return Money::of($q);
    }

    private function periodEnd(string $period): string
    {
        return date('Y-m-t', strtotime($period.'-01'));
    }
}
