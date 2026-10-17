<?php

namespace App\Modules\Sales\Events;

use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SalePayment;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Pembayaran susulan tercatat (cicilan/pelunasan piutang).
 * Konsumen: Accounting M.9 — jurnal Dr Kas/Bank / Cr Piutang.
 * Pembayaran awal saat penjualan dibuat tercakup jurnal penjualan.
 */
class SalePaymentRecorded
{
    use Dispatchable;

    public function __construct(
        public readonly SalePayment $payment,
        public readonly Sale $sale,
    ) {}
}
