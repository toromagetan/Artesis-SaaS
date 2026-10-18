<?php

use App\Modules\Accounting\Services\FixedAssetService;
use App\Modules\Accounting\Services\PostingService;
use App\Modules\Accounting\Support\Chart;
use App\Modules\Comms\Services\WaAutomationService;
use App\Modules\Inventory\Services\FifoService;
use App\Modules\Membership\Services\PointsService;
use App\Support\Money;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Penyusutan aset tetap bulanan (F1.4): idempoten per aset-periode,
 * aman dijalankan ulang / dijadwalkan. Periode bawaan = bulan ini.
 */
Artisan::command('lentera:penyusutan {periode?}', function () {
    $period = $this->argument('periode') ?? now()->format('Y-m');
    if (preg_match('/^\d{4}-\d{2}$/', (string) $period) !== 1) {
        $this->error('Format periode harus YYYY-MM.');

        return 1;
    }
    $result = app(FixedAssetService::class)->depreciatePeriod(null, (string) $period);
    $this->info("Penyusutan {$period}: {$result['posted']} jurnal, total ".$result['total']->format());

    return 0;
})->purpose('Jalankan penyusutan aset tetap satu periode (YYYY-MM)');

/*
 * Otomasi WhatsApp (F3.5): sapaan purnajual H+7 sesudah Complete +
 * ucapan ulang tahun hari ini. Idempoten (anti-ganda di layanan);
 * dijadwalkan harian 08:00 — pengiriman sesungguhnya lewat antrean.
 */
Artisan::command('lentera:wa-otomatis', function () {
    $automation = app(WaAutomationService::class);
    $afterService = $automation->runAfterService();
    $birthdays = $automation->runBirthdays();
    $this->info("WA otomatis: {$afterService} purnajual, {$birthdays} ulang tahun.");

    return 0;
})->purpose('Kirim WA purnajual (H+7) dan ucapan ulang tahun hari ini');

Schedule::command('lentera:wa-otomatis')->dailyAt('08:00');

/*
 * Kedaluwarsa poin membership (F3.6): sisa lot earn yang masa
 * berlakunya lewat menjadi baris expire + jurnal pembalik liabilitas.
 * Idempoten — lot yang sudah kedaluwarsa sisanya nol.
 */
Artisan::command('lentera:poin-kedaluwarsa', function () {
    $created = app(PointsService::class)->expireRun();
    $this->info("Poin kedaluwarsa: {$created} baris expire.");

    return 0;
})->purpose('Kedaluwarsakan poin membership yang masa berlakunya lewat');

Schedule::command('lentera:poin-kedaluwarsa')->dailyAt('00:30');

/*
 * Migrasi costing FIFO (F4.2, ADR-014): memainkan ulang seluruh
 * riwayat stock_movements dari keadaan kosong dengan aturan live
 * yang sama (FifoService::rebuildFromHistory), lalu menjurnal
 * selisih nilai lapisan vs saldo Persediaan buku besar per cabang
 * sebagai penyesuaian pembuka lewat HPP (postOnce 'fifo_opening'
 * per cabang — aman dijalankan ulang).
 */
Artisan::command('lentera:fifo-migrasi', function () {
    $fifo = app(FifoService::class);
    $posting = app(PostingService::class);
    $adjusted = DB::transaction(function () use ($fifo, $posting) {
        $values = $fifo->rebuildFromHistory();
        $count = 0;
        foreach ($values as $branchId => $layerValue) {
            $gl = Money::fromDecimal((string) DB::table('journal_lines as jl')
                ->join('accounts as a', 'a.id', '=', 'jl.account_id')
                ->where('a.code', Chart::PERSEDIAAN)
                ->where('jl.branch_id', $branchId)
                ->selectRaw('COALESCE(SUM(jl.debit - jl.credit), 0) as balance')
                ->value('balance'));
            $diff = $layerValue->sub($gl);
            if ($diff->isZero()) {
                continue;
            }
            $amount = $diff->abs()->toDecimalString();
            $lines = $diff->isPositive()
                ? [
                    ['account' => Chart::PERSEDIAAN, 'branch_id' => $branchId, 'debit' => $amount, 'memo' => 'Penyesuaian pembuka FIFO'],
                    ['account' => Chart::HPP, 'branch_id' => $branchId, 'credit' => $amount, 'memo' => 'Penyesuaian pembuka FIFO'],
                ]
                : [
                    ['account' => Chart::HPP, 'branch_id' => $branchId, 'debit' => $amount, 'memo' => 'Penyesuaian pembuka FIFO'],
                    ['account' => Chart::PERSEDIAAN, 'branch_id' => $branchId, 'credit' => $amount, 'memo' => 'Penyesuaian pembuka FIFO'],
                ];
            $posting->postOnce('fifo_opening', $branchId, now(), $lines, 'Penyesuaian pembuka FIFO');
            $count++;
        }

        return $count;
    });
    $this->info("Migrasi FIFO selesai: {$adjusted} cabang dengan jurnal penyesuaian pembuka.");

    return 0;
})->purpose('Bangun lapisan FIFO dari riwayat gerakan + jurnal penyesuaian pembuka');
