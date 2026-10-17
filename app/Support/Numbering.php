<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Penomoran dokumen (Blueprint E.4): PREFIX-YYYYMM-NNNN, urut per bulan
 * per tipe, TIDAK pernah dipakai ulang (nomor void tetap tercatat).
 * Penghitung dikunci barisnya (lockForUpdate) — WAJIB dipanggil di dalam
 * transaksi DB dokumen yang bersangkutan.
 */
class Numbering
{
    public static function next(string $prefix, ?\DateTimeInterface $date = null): string
    {
        $period = ($date ?? now())->format('Ym');

        $counter = DB::table('document_counters')
            ->where('prefix', $prefix)
            ->where('period', $period)
            ->lockForUpdate()
            ->first();

        if ($counter === null) {
            DB::table('document_counters')->insert([
                'prefix' => $prefix,
                'period' => $period,
                'last_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $counter = DB::table('document_counters')
                ->where('prefix', $prefix)
                ->where('period', $period)
                ->lockForUpdate()
                ->first();
        }

        $next = $counter->last_number + 1;
        DB::table('document_counters')
            ->where('id', $counter->id)
            ->update(['last_number' => $next, 'updated_at' => now()]);

        return sprintf('%s-%s-%04d', $prefix, $period, $next);
    }
}
