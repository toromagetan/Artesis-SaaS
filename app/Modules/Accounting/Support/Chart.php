<?php

namespace App\Modules\Accounting\Support;

/**
 * Kode bagan akun bawaan (Blueprint E.1). Semua template posting
 * menyelesaikan akun lewat konstanta ini — tidak ada string kode
 * yang tersebar di listener/service lain.
 */
final class Chart
{
    public const KAS = '1-1100';

    public const BANK = '1-1200';

    public const PIUTANG_PELANGGAN = '1-1300';

    public const PERSEDIAAN = '1-1400';

    public const PIUTANG_KREDIT_SUPPLIER = '1-1500';

    public const AKUMULASI_PENYUSUTAN = '1-1600';

    public const PIUTANG_ASURANSI = '1-1700';

    public const UTANG_SUPPLIER = '2-1100';

    public const LIABILITAS_POIN = '2-1200';

    public const UTANG_KOMISI = '2-1300';

    public const UTANG_INSENTIF = '2-1600';

    public const PPN_KELUARAN = '2-1400';

    public const KREDIT_TOKO = '2-1500';

    public const MODAL_PEMILIK = '3-1000';

    public const MODAL_PERSEDIAAN_AWAL = '3-1100';

    public const PENDAPATAN_PENJUALAN = '4-1100';

    public const RETUR_PENJUALAN = '4-1200';

    public const HPP = '5-1100';

    public const BEBAN_SELIH_OPNAME = '5-1200';

    public const BEBAN_LOYALITAS = '5-1300';

    public const BEBAN_KOMISI = '5-1400';

    public const BEBAN_INSENTIF = '5-1800';

    public const BEBAN_GAJI = '5-1500';

    public const BEBAN_PENYUSUTAN = '5-1600';

    public const BEBAN_GARANSI = '5-1700';

    public const PENDAPATAN_GARANSI = '4-1300';

    /** Peta metode bayar → akun kas/bank (E.1: dipilih dari metode). */
    public static function tenderAccount(string $method): string
    {
        return match ($method) {
            'cash' => self::KAS,
            'points' => self::LIABILITAS_POIN,
            'store_credit' => self::KREDIT_TOKO,
            'insurance' => self::PIUTANG_ASURANSI,
            default => self::BANK, // debit, qris, transfer, card, giro, other
        };
    }
}
