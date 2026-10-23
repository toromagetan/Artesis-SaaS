<?php

use App\Modules\Accounting\Services\FixedAssetService;
use App\Modules\Accounting\Services\PostingService;
use App\Modules\Accounting\Support\Chart;
use App\Modules\Comms\Jobs\SendWhatsAppMessage;
use App\Modules\Comms\Models\WaMessage;
use App\Modules\Comms\Services\GatewayFactory;
use App\Modules\Comms\Services\WaAutomationService;
use App\Modules\Inventory\Services\FifoService;
use App\Modules\Master\Models\Setting;
use App\Modules\Membership\Services\PointsService;
use App\Modules\Platform\Services\AuditLogger;
use App\Modules\Platform\Services\BackupService;
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
    $installments = $automation->runInstallmentReminders();
    $this->info("WA otomatis: {$afterService} purnajual, {$birthdays} ulang tahun, {$installments} pengingat cicilan.");

    return 0;
})->purpose('Kirim WA purnajual (H+7) dan ucapan ulang tahun hari ini');

Schedule::command('lentera:wa-otomatis')->dailyAt('08:00');

/*
 * Sapu antrean WA (audit 23 Okt 2026 C1): pesan keluar yang masih
 * 'queued' sesudah 15 menit — termasuk yang terantre sebelum
 * gateway dikonfigurasi (job pertamanya selesai diam-diam tanpa
 * gateway) — didispatch ulang BILA gateway aktif. Kesehatan
 * antrean (job gagal / pesan menggantung > 24 jam) dicatat ke
 * log aktivitas sebagai wa.queue.health agar terlihat di UI.
 */
Artisan::command('lentera:wa-sapu', function () {
    $factory = app(GatewayFactory::class);
    $stale = WaMessage::where('direction', 'out')
        ->where('status', 'queued')
        ->where('created_at', '<=', now()->subMinutes(15));

    $redispatched = 0;
    if ($factory->active() !== null) {
        $ids = (clone $stale)->orderBy('id')->limit(500)->pluck('id');
        foreach ($ids as $id) {
            SendWhatsAppMessage::dispatch($id);
            $redispatched++;
        }
    }

    $stuck24h = WaMessage::where('direction', 'out')
        ->where('status', 'queued')
        ->where('created_at', '<=', now()->subDay())
        ->count();
    $failedJobs = DB::table('failed_jobs')->count();
    if ($stuck24h > 0 || $failedJobs > 0) {
        app(AuditLogger::class)->log(
            action: 'wa.queue.health',
            subjectType: 'wa_queue',
            subjectId: null,
            newValues: ['stuck_over_24h' => $stuck24h, 'failed_jobs' => $failedJobs, 'redispatched' => $redispatched],
        );
    }
    $this->info("Sapu WA: {$redispatched} didispatch ulang; menggantung >24 jam: {$stuck24h}; job gagal: {$failedJobs}.");

    return 0;
})->purpose('Dispatch ulang pesan WA queued yang menggantung + catat kesehatan antrean');

Schedule::command('lentera:wa-sapu')->hourly();

/*
 * Backup otomatis harian (audit 23 Okt 2026): pukul 02:00,
 * awalan 'auto', retensi 14 berkas otomatis terbaru (backup
 * manual tidak pernah disentuh retensi). Offsite (Google Drive)
 * aktif hanya bila pemilik memasang kredensial — selama belum,
 * pengaktifan yang diminta lewat Pengaturan dicatat sebagai
 * peringatan audit, bukan diam-diam diabaikan.
 */
Artisan::command('lentera:backup-otomatis', function () {
    $service = app(BackupService::class);
    $name = $service->createSystem();
    $pruned = $service->pruneAuto(14);
    if (filter_var(Setting::value('backup.offsite_enabled', '0'), FILTER_VALIDATE_BOOLEAN)) {
        app(AuditLogger::class)->log(
            action: 'backup.offsite_unconfigured',
            subjectType: 'Backup',
            subjectId: null,
            newValues: ['file' => $name],
        );
        $this->warn('Offsite diminta tetapi kredensial Drive belum terpasang — backup hanya tersimpan lokal.');
    }
    $this->info("Backup otomatis: {$name}; retensi menghapus {$pruned} berkas.");

    return 0;
})->purpose('Backup otomatis harian + retensi (offsite bila kredensial Drive terpasang)');

Schedule::command('lentera:backup-otomatis')->dailyAt('02:00');

/*
 * Penyusutan terjadwal (audit 23 Okt 2026 C3): tanggal 1 pukul
 * 01:00 untuk periode BULAN SEBELUMNYA (perintahnya idempoten
 * per aset-periode sehingga aman terhadap jalan ulang).
 */
Schedule::call(function () {
    Artisan::call('lentera:penyusutan', ['periode' => now()->subMonth()->format('Y-m')]);
})->monthlyOn(1, '01:00');

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

/*
 * Preflight migrasi indeks unik journals.reversal_of_id (LNT-005,
 * migrasi 2026_10_23_000045). READ-ONLY: hanya SELECT. Migrasi
 * 000045 gagal ERROR 1062 di tengah jalan bila database lama
 * memuat reversal_of_id non-NULL ganda; perintah ini wajib
 * dijalankan (dan lulus) SEBELUM migrasi pada database mana pun
 * yang datanya berasal dari sebelum perbaikan LNT-005. Exit 0 =
 * aman; exit 1 = duplikat ditemukan, JANGAN migrasi dulu —
 * bersihkan dari backup terverifikasi (lihat README "Prosedur
 * migrasi indeks unik reversal_of_id").
 */
Artisan::command('lentera:preflight-reversal', function () {
    $dupes = DB::table('journals')
        ->selectRaw('reversal_of_id, COUNT(*) AS jumlah')
        ->whereNotNull('reversal_of_id')
        ->groupBy('reversal_of_id')
        ->having('jumlah', '>', 1)
        ->orderBy('reversal_of_id')
        ->get();

    if ($dupes->isEmpty()) {
        $this->info('PREFLIGHT LNT-005 AMAN: tidak ada reversal_of_id ganda — migrasi 000045 aman dijalankan.');

        return 0;
    }

    $this->error('PREFLIGHT LNT-005 GAGAL: ditemukan jurnal asal dengan lebih dari satu jurnal pembalik:');
    foreach ($dupes as $dupe) {
        $this->line("  reversal_of_id={$dupe->reversal_of_id} jumlah={$dupe->jumlah}");
    }
    $this->warn('JANGAN jalankan migrasi 000045 sebelum duplikat dibersihkan dari backup yang terverifikasi.');

    return 1;
})->purpose('Preflight read-only duplikat reversal_of_id sebelum migrasi indeks unik 000045 (LNT-005)');
