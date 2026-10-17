<?php

namespace Database\Seeders;

use App\Modules\Accounting\Models\Account;
use Illuminate\Database\Seeder;

/**
 * Bagan akun bawaan (Blueprint E.1 — kode selaras pemetaan Coretax).
 * Idempoten (updateOrCreate per kode).
 */
class AccountingSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['1-1100', 'Kas', 'asset'],
            ['1-1200', 'Bank', 'asset'],
            ['1-1300', 'Piutang Pelanggan', 'asset'],
            ['1-1400', 'Persediaan', 'asset'],
            ['1-1500', 'Piutang Kredit Supplier', 'asset'],
            ['1-1600', 'Akumulasi Penyusutan', 'asset'],
            ['2-1100', 'Utang Supplier', 'liability'],
            ['2-1200', 'Liabilitas Poin', 'liability'],
            ['2-1300', 'Utang Komisi', 'liability'],
            ['2-1400', 'PPN Keluaran', 'liability'],
            ['2-1500', 'Kredit Toko Pelanggan', 'liability'],
            ['3-1000', 'Modal Pemilik', 'equity'],
            ['3-1100', 'Modal Persediaan Awal', 'equity'],
            ['4-1100', 'Pendapatan Penjualan', 'revenue'],
            ['4-1200', 'Retur Penjualan', 'revenue'],
            ['5-1100', 'Harga Pokok Penjualan', 'expense'],
            ['5-1200', 'Beban Selisih Opname', 'expense'],
            ['5-1300', 'Beban Program Loyalitas', 'expense'],
            ['5-1400', 'Beban Komisi', 'expense'],
            ['5-1500', 'Beban Gaji', 'expense'],
            ['5-1600', 'Beban Penyusutan', 'expense'],
        ];
        foreach ($accounts as [$code, $name, $group]) {
            Account::updateOrCreate(['code' => $code], ['name' => $name, 'group' => $group, 'is_active' => true]);
        }
    }
}
