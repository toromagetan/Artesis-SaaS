<?php

use App\Modules\Accounting\Services\FixedAssetService;
use App\Modules\Comms\Services\WaAutomationService;
use App\Modules\Membership\Services\PointsService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
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
