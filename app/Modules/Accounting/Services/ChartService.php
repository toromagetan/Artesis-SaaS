<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\Account;

/**
 * Bagan akun per ember tenant (ISO-2, audit isolasi menyeluruh
 * 18 Nov 2026). Definisi templat kanonis tinggal di sini — satu
 * sumber untuk ember platform/mandiri (tenant_id NULL) dan salinan
 * milik setiap tenant.
 *
 * Penanaman bersifat updateOrCreate per (ember, kode): menjalankan
 * ulang tidak menggandakan dan menyelaraskan nama/kelompok templat.
 * Akun tambahan buatan pemilik ember (kode di luar templat) tidak
 * disentuh.
 */
class ChartService
{
    /** @var list<array{0: string, 1: string, 2: string}> [kode, nama, kelompok] */
    public const TEMPLATE = [
        ['1-1100', 'Kas', 'asset'],
        ['1-1200', 'Bank', 'asset'],
        ['1-1300', 'Piutang Pelanggan', 'asset'],
        ['1-1400', 'Persediaan', 'asset'],
        ['1-1500', 'Piutang Kredit Supplier', 'asset'],
        ['1-1600', 'Akumulasi Penyusutan', 'asset'],
        ['1-1700', 'Piutang Asuransi', 'asset'],
        ['2-1100', 'Utang Supplier', 'liability'],
        ['2-1200', 'Liabilitas Poin', 'liability'],
        ['2-1300', 'Utang Komisi', 'liability'],
        ['2-1400', 'PPN Keluaran', 'liability'],
        ['2-1500', 'Kredit Toko Pelanggan', 'liability'],
        ['3-1000', 'Modal Pemilik', 'equity'],
        ['3-1100', 'Modal Persediaan Awal', 'equity'],
        ['4-1100', 'Pendapatan Penjualan', 'revenue'],
        ['4-1200', 'Retur Penjualan', 'revenue'],
        ['4-1300', 'Pendapatan Garansi', 'revenue'],
        ['5-1100', 'Harga Pokok Penjualan', 'expense'],
        ['5-1200', 'Beban Selisih Opname', 'expense'],
        ['5-1300', 'Beban Program Loyalitas', 'expense'],
        ['5-1400', 'Beban Komisi', 'expense'],
        ['5-1500', 'Beban Gaji', 'expense'],
        ['5-1600', 'Beban Penyusutan', 'expense'],
        ['5-1700', 'Beban Garansi', 'expense'],
    ];

    /** Tanam/selaraskan bagan templat ke dalam satu ember (null = platform/mandiri). */
    public function seedForBucket(?int $tenantId): void
    {
        foreach (self::TEMPLATE as [$code, $name, $group]) {
            Account::updateOrCreate(
                ['code' => $code, 'tenant_id' => $tenantId],
                ['name' => $name, 'group' => $group, 'is_active' => true],
            );
        }
    }

    /** Salinan bagan untuk satu tenant (dipanggil saat tenant dibuat). */
    public function seedForTenant(int $tenantId): void
    {
        $this->seedForBucket($tenantId);
    }
}
