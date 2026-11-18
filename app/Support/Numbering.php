<?php

namespace App\Support;

use App\Modules\Identity\Services\TenantContext;
use App\Modules\Master\Models\Branch;
use Illuminate\Support\Facades\DB;

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
 */
class Numbering
{
    public static function next(string $prefix, ?\DateTimeInterface $date = null, ?int $tenantId = null, bool $tenantSet = false): string
    {
        $period = ($date ?? now())->format('Ym');

        if (! $tenantSet) {
            $context = TenantContext::current();
            $tenantId = $context->isValid() ? $context->tenantId() : null;
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
     * Nomor untuk dokumen bercabang: ember diturunkan dari tenant
     * pemilik cabang (cabang adalah bukti kepemilikan yang sah),
     * bukan dari aktor — jalur sistem tanpa aktor tetap benar.
     */
    public static function nextForBranch(string $prefix, ?int $branchId, ?\DateTimeInterface $date = null): string
    {
        $tenantId = $branchId === null
            ? null
            : Branch::whereKey($branchId)->value('tenant_id');

        return static::next($prefix, $date, $tenantId !== null ? (int) $tenantId : null, true);
    }
}
