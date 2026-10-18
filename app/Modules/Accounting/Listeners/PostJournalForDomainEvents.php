<?php

namespace App\Modules\Accounting\Listeners;

use App\Modules\Accounting\Models\Journal;
use App\Modules\Accounting\Services\PostingService;
use App\Modules\Accounting\Support\Chart;
use App\Modules\Inventory\Events\InitialStockImported;
use App\Modules\Inventory\Events\OpnameApproved;
use App\Modules\Inventory\Models\StockLayer;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Services\FifoService;
use App\Modules\Purchasing\Events\PurchasePaymentPosted;
use App\Modules\Purchasing\Events\PurchaseReturned;
use App\Modules\Purchasing\Events\ReceiptStored;
use App\Modules\Purchasing\Models\Receipt;
use App\Modules\Sales\Events\SaleCompleted;
use App\Modules\Sales\Events\SalePaymentRecorded;
use App\Modules\Sales\Events\SaleReturned;
use App\Modules\Sales\Events\SaleVoided;
use App\Modules\Sales\Models\Sale;
use App\Support\Money;

/**
 * Penerjemah peristiwa domain → jurnal (Blueprint D.9 + peta E.1).
 * Berjalan sinkron di dalam transaksi peristiwa sumber: kegagalan
 * posting menggulung transaksi bisnisnya (nol tercatat-sebagian).
 * Transfer antar cabang TIDAK menjurnal (pergerakan internal).
 */
class PostJournalForDomainEvents
{
    public function __construct(private readonly PostingService $posting) {}

    public function onSaleCompleted(SaleCompleted $event): void
    {
        $sale = $event->sale->loadMissing(['items.product', 'payments']);
        $total = Money::fromDecimal((string) $sale->total_amount);
        $tax = Money::fromDecimal((string) $sale->tax_amount);
        $revenue = $total->sub($tax);

        $lines = [];
        // Sisi debit tender: pembayaran awal per kelompok metode.
        $byMethod = [];
        foreach ($sale->payments as $payment) {
            if ($payment->reversal_of_id !== null) {
                continue;
            }
            $byMethod[$payment->method] = ($byMethod[$payment->method] ?? Money::zero())
                ->add(Money::fromDecimal((string) $payment->amount));
        }
        $paidTotal = Money::zero();
        foreach ($byMethod as $method => $sum) {
            if ($sum->isPositive()) {
                $lines[] = ['account' => Chart::tenderAccount($method), 'branch_id' => $sale->branch_id, 'debit' => $sum->toDecimalString(), 'memo' => $sale->number];
                $paidTotal = $paidTotal->add($sum);
            }
        }
        $receivable = $total->sub($paidTotal);
        if ($receivable->isPositive()) {
            $lines[] = ['account' => Chart::PIUTANG_PELANGGAN, 'branch_id' => $sale->branch_id, 'debit' => $receivable->toDecimalString(), 'memo' => $sale->number];
        }
        if ($revenue->isPositive()) {
            $lines[] = ['account' => Chart::PENDAPATAN_PENJUALAN, 'branch_id' => $sale->branch_id, 'credit' => $revenue->toDecimalString(), 'memo' => $sale->number];
        }
        if ($tax->isPositive()) {
            $lines[] = ['account' => Chart::PPN_KELUARAN, 'branch_id' => $sale->branch_id, 'credit' => $tax->toDecimalString(), 'memo' => $sale->number];
        }
        // HPP FIFO (ADR-014): jumlah total_cost gerakan 'sale'
        // penjualan ini (konsumsi lapisan saat transaksi; bagian
        // backorder yang belum teralokasi menyusul sebagai jurnal
        // fifo_coverage saat penerimaan penutup tiba). returnedQty
        // tidak dikurangkan di sini — retur berjalan sebagai jurnal
        // sale_return terpisah yang mengkredit HPP kembali.
        // (Satu-satunya sumber: gerakan yang ditulis SyncStockWithSales
        // pada transaksi yang sama. Nol di sini berarti backorder
        // murni — HPP-nya DITANGGUHKAN per ADR-014, bukan ditebak
        // dari cost_price master.)
        $cogs = Money::fromDecimal((string) StockMovement::withoutGlobalScopes()
            ->where('reference_type', 'sale')
            ->where('reference_id', $sale->id)
            ->where('reason', 'sale')
            ->sum('total_cost'));
        if ($cogs->isPositive()) {
            $lines[] = ['account' => Chart::HPP, 'branch_id' => $sale->branch_id, 'debit' => $cogs->toDecimalString(), 'memo' => $sale->number];
            $lines[] = ['account' => Chart::PERSEDIAAN, 'branch_id' => $sale->branch_id, 'credit' => $cogs->toDecimalString(), 'memo' => $sale->number];
        }
        if ($lines === []) {
            return;
        }
        $this->posting->postOnce('sale', $sale->id, $sale->sale_date, $lines, 'Penjualan '.$sale->number);
    }

    public function onSalePaymentRecorded(SalePaymentRecorded $event): void
    {
        $payment = $event->payment;
        $sale = $event->sale;
        $this->posting->postOnce('sale_payment', $payment->id, $payment->paid_at ?? now(), [
            ['account' => Chart::tenderAccount($payment->method), 'branch_id' => $sale->branch_id, 'debit' => (string) $payment->amount, 'memo' => $sale->number],
            ['account' => Chart::PIUTANG_PELANGGAN, 'branch_id' => $sale->branch_id, 'credit' => (string) $payment->amount, 'memo' => $sale->number],
        ], 'Pembayaran '.$sale->number);
    }

    public function onSaleVoided(SaleVoided $event): void
    {
        $sale = $event->sale;
        $journals = Journal::where(function ($q) use ($sale) {
            $q->where(fn ($w) => $w->where('source_type', 'sale')->where('source_id', $sale->id))
                ->orWhere(fn ($w) => $w->where('source_type', 'sale_payment')
                    ->whereIn('source_id', $sale->payments()->pluck('id')));
        })->get();
        foreach ($journals as $journal) {
            if (! $journal->isReversed()) {
                $this->posting->reverse($journal->load('lines.account'), 'Void penjualan '.$sale->number);
            }
        }
    }

    public function onSaleReturned(SaleReturned $event): void
    {
        $return = $event->saleReturn;
        $sale = Sale::withoutGlobalScopes()->with('items.product')->findOrFail($return->sale_id);
        $total = Money::fromDecimal((string) $return->total_amount);

        $lines = [];
        if ($total->isPositive()) {
            $lines[] = ['account' => Chart::RETUR_PENJUALAN, 'branch_id' => $sale->branch_id, 'debit' => $total->toDecimalString(), 'memo' => $return->number];
            $creditAccount = $return->refund_method === 'store_credit' ? Chart::KREDIT_TOKO : Chart::tenderAccount($return->refund_method);
            $lines[] = ['account' => $creditAccount, 'branch_id' => $sale->branch_id, 'credit' => $total->toDecimalString(), 'memo' => $return->number];
        }
        // Restock hanya item resale, pada biaya FIFO asal gerakan
        // masuknya (ADR-014) — dicap InventoryService ke gerakan
        // 'sale_return' retur ini.
        $restock = Money::fromDecimal((string) StockMovement::withoutGlobalScopes()
            ->where('reference_type', 'sale_return')
            ->where('reference_id', $return->id)
            ->where('reason', 'sale_return')
            ->sum('total_cost'));
        if ($restock->isPositive()) {
            $lines[] = ['account' => Chart::PERSEDIAAN, 'branch_id' => $sale->branch_id, 'debit' => $restock->toDecimalString(), 'memo' => $return->number];
            $lines[] = ['account' => Chart::HPP, 'branch_id' => $sale->branch_id, 'credit' => $restock->toDecimalString(), 'memo' => $return->number];
        }
        if ($lines === []) {
            return;
        }
        $this->posting->postOnce('sale_return', $return->id, $return->created_at ?? now(), $lines, 'Retur penjualan '.$return->number);
    }

    public function onReceiptStored(ReceiptStored $event): void
    {
        $receipt = $event->receipt;
        $po = $receipt->purchaseOrder()->withoutGlobalScopes()->firstOrFail();
        $total = $receipt->totalAmount();
        if (! $total->isPositive()) {
            return;
        }
        $this->posting->postOnce('receipt', $receipt->id, $receipt->received_date, [
            ['account' => Chart::PERSEDIAAN, 'branch_id' => $po->branch_id, 'debit' => $total->toDecimalString(), 'memo' => $receipt->number],
            ['account' => Chart::UTANG_SUPPLIER, 'branch_id' => $po->branch_id, 'credit' => $total->toDecimalString(), 'memo' => $receipt->number],
        ], 'Penerimaan '.$receipt->number);

        // HPP tertangguh (ADR-014): lapisan penerimaan ini pertama
        // menutupi backorder lama — nilai penutupnya adalah HPP
        // penjualan-penjualan terdahulu yang baru kini tercatat.
        $layerIds = StockLayer::whereIn('receipt_item_id', $receipt->items()->pluck('id'))->pluck('id')->all();
        $coverage = app(FifoService::class)->coverageValueForLayers($layerIds);
        if ($coverage->isPositive()) {
            $this->posting->postOnce('fifo_coverage', $receipt->id, $receipt->received_date, [
                ['account' => Chart::HPP, 'branch_id' => $po->branch_id, 'debit' => $coverage->toDecimalString(), 'memo' => $receipt->number],
                ['account' => Chart::PERSEDIAAN, 'branch_id' => $po->branch_id, 'credit' => $coverage->toDecimalString(), 'memo' => $receipt->number],
            ], 'HPP tertangguh penerimaan '.$receipt->number);
        }
    }

    public function onPurchaseReturned(PurchaseReturned $event): void
    {
        $return = $event->purchaseReturn;
        $receipt = Receipt::findOrFail($return->receipt_id);
        $po = $receipt->purchaseOrder()->withoutGlobalScopes()->firstOrFail();
        $total = Money::fromDecimal((string) $return->total_amount);
        if (! $total->isPositive()) {
            return;
        }
        $debitAccount = $return->credit_status === 'supplier_credit'
            ? Chart::PIUTANG_KREDIT_SUPPLIER
            : Chart::UTANG_SUPPLIER;
        // Kredit Persediaan = nilai tercatat FIFO yang keluar
        // (ADR-014); sisi klaim supplier tetap nilai dokumen retur.
        // Selisih keduanya (bila konsumsi tumpah ke lapisan lain)
        // diselesaikan ke HPP agar jurnal selalu seimbang.
        $carrying = Money::fromDecimal((string) StockMovement::withoutGlobalScopes()
            ->where('reference_type', 'purchase_return')
            ->where('reference_id', $return->id)
            ->where('reason', 'purchase_return')
            ->sum('total_cost'));
        $lines = [
            ['account' => $debitAccount, 'branch_id' => $po->branch_id, 'debit' => $total->toDecimalString(), 'memo' => $return->number],
            ['account' => Chart::PERSEDIAAN, 'branch_id' => $po->branch_id, 'credit' => $carrying->toDecimalString(), 'memo' => $return->number],
        ];
        $diff = $total->sub($carrying);
        if ($diff->isPositive()) {
            $lines[] = ['account' => Chart::HPP, 'branch_id' => $po->branch_id, 'credit' => $diff->toDecimalString(), 'memo' => $return->number];
        } elseif ($diff->isNegative()) {
            $lines[] = ['account' => Chart::HPP, 'branch_id' => $po->branch_id, 'debit' => $diff->abs()->toDecimalString(), 'memo' => $return->number];
        }
        $this->posting->postOnce('purchase_return', $return->id, $return->created_at ?? now(), $lines, 'Retur pembelian '.$return->number);
    }

    public function onPurchasePaymentPosted(PurchasePaymentPosted $event): void
    {
        $payment = $event->payment;
        $this->posting->postOnce('purchase_payment', $payment->id, $payment->payment_date, [
            ['account' => Chart::UTANG_SUPPLIER, 'debit' => (string) $payment->amount, 'memo' => $payment->number],
            ['account' => Chart::tenderAccount($payment->method), 'credit' => (string) $payment->amount, 'memo' => $payment->number],
        ], 'Pembayaran supplier '.$payment->number);
    }

    /**
     * Opname disetujui (F4.1, peta E.1): nilai selisih neto dari
     * gerakan 'opname' opname ini. Harga gerakan dibaca dari
     * total_cost gerakan bila sudah terisi (FIFO, F4.2); sebelum
     * itu fallback = selisih × cost_price master — keduanya bertanda
     * mengikuti arah selisih (kurang negatif, lebih positif).
     * Neto kurang → Dr Beban Selisih Opname / Cr Persediaan;
     * neto lebih sebaliknya.
     */
    public function onOpnameApproved(OpnameApproved $event): void
    {
        $opname = $event->opname;
        $net = Money::zero();
        foreach ($opname->items()->with(['movement', 'product'])->get() as $item) {
            if ($item->difference === 0) {
                continue;
            }
            if ($item->movement !== null && $item->movement->total_cost !== null) {
                $value = Money::fromDecimal((string) $item->movement->total_cost);
                $net = $item->difference < 0 ? $net->sub($value) : $net->add($value);
            } elseif ($item->product !== null) {
                $net = $net->add(Money::fromDecimal((string) $item->product->cost_price)->mul($item->difference));
            }
        }
        if ($net->isZero()) {
            return;
        }
        $amount = $net->isNegative() ? $net->mul(-1) : $net;
        $lines = $net->isNegative()
            ? [
                ['account' => Chart::BEBAN_SELIH_OPNAME, 'branch_id' => $opname->branch_id, 'debit' => $amount->toDecimalString(), 'memo' => 'Opname #'.$opname->id],
                ['account' => Chart::PERSEDIAAN, 'branch_id' => $opname->branch_id, 'credit' => $amount->toDecimalString(), 'memo' => 'Opname #'.$opname->id],
            ]
            : [
                ['account' => Chart::PERSEDIAAN, 'branch_id' => $opname->branch_id, 'debit' => $amount->toDecimalString(), 'memo' => 'Opname #'.$opname->id],
                ['account' => Chart::BEBAN_SELIH_OPNAME, 'branch_id' => $opname->branch_id, 'credit' => $amount->toDecimalString(), 'memo' => 'Opname #'.$opname->id],
            ];
        $this->posting->postOnce('stock_opname', $opname->id, $opname->approved_at ?? now(), $lines, 'Opname #'.$opname->id);
    }

    public function onInitialStockImported(InitialStockImported $event): void
    {
        $this->posting->postOnce('initial_stock_import', $event->batchReferenceId, now(), [
            ['account' => Chart::PERSEDIAAN, 'branch_id' => $event->branch->id, 'debit' => $event->totalValue, 'memo' => $event->branch->name],
            ['account' => Chart::MODAL_PERSEDIAAN_AWAL, 'branch_id' => $event->branch->id, 'credit' => $event->totalValue, 'memo' => $event->branch->name],
        ], 'Impor stok awal '.$event->branch->name);

        // Penutup backorder oleh lapisan impor (ADR-014), sama
        // seperti penerimaan: HPP tertangguh penjualan terdahulu.
        $layerIds = StockLayer::whereHas('sourceMovement', function ($q) use ($event) {
            $q->where('reference_type', 'initial_stock_import')
                ->where('reference_id', $event->batchReferenceId)
                ->where('branch_id', $event->branch->id);
        })->pluck('id')->all();
        $coverage = app(FifoService::class)->coverageValueForLayers($layerIds);
        if ($coverage->isPositive()) {
            $this->posting->postOnce('fifo_coverage_import', $event->batchReferenceId, now(), [
                ['account' => Chart::HPP, 'branch_id' => $event->branch->id, 'debit' => $coverage->toDecimalString(), 'memo' => $event->branch->name],
                ['account' => Chart::PERSEDIAAN, 'branch_id' => $event->branch->id, 'credit' => $coverage->toDecimalString(), 'memo' => $event->branch->name],
            ], 'HPP tertangguh impor stok awal '.$event->branch->name);
        }
    }
}
