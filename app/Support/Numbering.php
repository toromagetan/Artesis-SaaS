<?php

namespace App\Support;

use App\Modules\Identity\Services\TenantContext;
use App\Modules\Master\Models\Branch;
use App\Modules\Platform\Models\Tenant;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Penomoran dokumen (Blueprint E.4): PREFIX-YYYYMM-NNNN, urut per
 * bulan per tipe, TIDAK pernah dipakai ulang (nomor void tetap
 * tercatat). WAJIB dipanggil di dalam transaksi DB dokumen yang
 * bersangkutan — kenaikan penghitung ikut ter-commit/ter-rollback
 * bersama dokumennya.
 *
 * Kenaikan bersifat ATOMIK satu pernyataan (upsert atas kunci unik
 * (prefix, period, tenant_key)): pola lama select-lockForUpdate →
 * insert → update terbukti DEADLOCK (MySQL 1213) saat dua transaksi
 * bersamaan mencetak nomor pertama sebuah ember — keduanya
 * mengunci celah indeks yang sama lalu sama-sama menyisip. Upsert
 * mengambil kunci baris tunggal dengan urutan yang konsisten;
 * pencetak berikutnya menunggu commit pencetak sebelumnya, bukan
 * berdeadlock. Dibuktikan DocumentNumberingRaceTest.
 *
 * Ember per tenant (ISO-1, audit isolasi 18 Nov 2026): setiap
 * tenant memiliki tangga nomornya sendiri; ember NULL adalah milik
 * platform/instalasi mandiri. Pemanggil yang mengetahui tenant
 * dokumennya WAJIB meneruskannya secara eksplisit (atau lewat
 * nextForBranch); tanpa penanda eksplisit, ember diturunkan dari
 * konteks tenant ambient (aktor terautentikasi) — jalur sistem
 * tanpa aktor tidak boleh mengandalkan penurunan ini.
 *
 * Gagal-tertutup (verifikasi audit independen 18 Nov 2026 temuan
 * D): penurunan ambient hanya sah bila konteks membuktikan tenant
 * (mode TENANT tervalidasi) ATAU instalasi benar-benar mandiri
 * (belum ada tenant sama sekali, ember NULL adalah satu-satunya
 * ember). Pada instalasi bertenant, pemanggilan yang tidak dapat
 * membuktikan embernya DITOLAK — bukan diam-diam diarahkan ke
 * ember global. Seluruh titik panggil produksi saat ini eksplisit,
 * jadi jalur ambient yang tertolak selalu berarti kekeliruan
 * pemanggil baru.
 */
class Numbering
{
    public static function next(string $prefix, ?\DateTimeInterface $date = null, ?int $tenantId = null, bool $tenantSet = false): string
    {
        $period = ($date ?? now())->format('Ym');

        if (! $tenantSet) {
            $tenantId = static::ambientTenantId();
        }

        $now = now();
        DB::table('document_counters')->upsert(
            [[
                'prefix' => $prefix,
                'period' => $period,
                'tenant_id' => $tenantId,
                'last_number' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]],
            ['prefix', 'period', 'tenant_key'],
            ['last_number' => DB::raw('last_number + 1'), 'updated_at' => $now],
        );

        $last = (int) DB::table('document_counters')
            ->where('prefix', $prefix)
            ->where('period', $period)
            ->when($tenantId === null, fn ($q) => $q->whereNull('tenant_id'), fn ($q) => $q->where('tenant_id', $tenantId))
            ->value('last_number');

        return sprintf('%s-%s-%04d', $prefix, $period, $last);
    }

    /**
     * Ember dari konteks ambient, gagal-tertutup: konteks TENANT
     * tervalidasi membuktikan embernya; instalasi tanpa tenant sama
     * sekali membuktikan ember NULL; selain itu ember tidak dapat
     * dibuktikan dan pemanggilan ditolak.
     */
    private static function ambientTenantId(): ?int
    {
        $context = TenantContext::current();
        if ($context->isValid()) {
            return $context->tenantId();
        }

        if (Tenant::count() === 0) {
            return null;
        }

        throw new RuntimeException(
            'Numbering: ember tenant tidak dapat dibuktikan dari konteks ambient '
            .'pada instalasi bertenant — teruskan tenant dokumen secara eksplisit.'
        );
    }

    /**
     * Nomor untuk dokumen bercabang: ember diturunkan dari tenant
     * pemilik cabang (cabang adalah bukti kepemilikan yang sah),
     * bukan dari aktor — jalur sistem tanpa aktor tetap benar.
     * Gagal-tertutup: cabang yang diberikan tetapi tidak ditemukan
     * DITOLAK (sebelumnya jatuh diam-diam ke ember global), dan
     * cabang null hanya sah pada instalasi mandiri murni.
     *
     * Pengerasan lanjutan (audit independen lanjutan 18 Nov 2026):
     * cabang yang ditemukan harus BENAR-BENAR membuktikan ember —
     * (1) cabang bertenant_id NULL pada instalasi bertenant tidak
     * membuktikan apa pun dan DITOLAK (sebelumnya diam-diam memakai
     * ember global); ember NULL dari cabang yatim hanya sah pada
     * instalasi mandiri murni; (2) tenant yang diklaim cabang harus
     * benar-benar ada; (3) bila konteks ambient adalah konteks
     * TENANT yang tervalidasi, cabang tenant lain DITOLAK —
     * pemanggil bertenant tidak dapat mencetak nomor ke ember
     * tenant lain lewat id cabang asing. Konteks sistem/platform/
     * mandiri tidak dibatasi aturan (3): di sana cabang itu sendiri
     * adalah bukti embernya (batas kepercayaan in-process ADR-022).
     */
    public static function nextForBranch(string $prefix, ?int $branchId, ?\DateTimeInterface $date = null): string
    {
        if ($branchId === null) {
            if (Tenant::count() > 0) {
                throw new RuntimeException(
                    'Numbering: nomor dokumen bercabang diminta tanpa cabang '
                    .'pada instalasi bertenant — ember tidak dapat dibuktikan.'
                );
            }

            return static::next($prefix, $date, null, true);
        }

        $branch = Branch::whereKey($branchId)->first();
        if ($branch === null) {
            throw new RuntimeException(
                "Numbering: cabang {$branchId} tidak ditemukan — ember dokumen tidak dapat dibuktikan."
            );
        }

        $branchTenantId = $branch->tenant_id !== null ? (int) $branch->tenant_id : null;

        if ($branchTenantId === null) {
            if (Tenant::count() > 0) {
                throw new RuntimeException(
                    "Numbering: cabang {$branchId} tidak memiliki tenant pada instalasi "
                    .'bertenant — ember dokumen tidak dapat dibuktikan.'
                );
            }

            return static::next($prefix, $date, null, true);
        }

        if (! Tenant::whereKey($branchTenantId)->exists()) {
            throw new RuntimeException(
                "Numbering: tenant {$branchTenantId} pemilik cabang {$branchId} tidak ditemukan "
                .'— ember dokumen tidak dapat dibuktikan.'
            );
        }

        $context = TenantContext::current();
        if ($context->isValid() && $context->tenantId() !== null
            && $context->tenantId() !== $branchTenantId) {
            throw new RuntimeException(
                "Numbering: cabang {$branchId} milik tenant {$branchTenantId}, bukan tenant "
                .'konteks pemanggil — nomor tidak dicetak ke ember tenant lain.'
            );
        }

        return static::next($prefix, $date, $branchTenantId, true);
    }
}
