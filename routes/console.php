<?php

use App\Modules\Accounting\Services\FixedAssetService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

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
