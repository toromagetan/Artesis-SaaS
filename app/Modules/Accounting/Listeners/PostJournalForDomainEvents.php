<?php

namespace App\Modules\Accounting\Listeners;

use App\Modules\Accounting\Models\Journal;
use App\Modules\Accounting\Services\PostingService;
use App\Modules\Accounting\Support\Chart;
use App\Modules\Inventory\Events\InitialStockImported;
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
        // HPP pada nilai modal item.
        $cogs = Money::zero();
        foreach ($sale->items as $item) {
            $cost = $item->product !== null
                ? Money::fromDecimal((string) $item->product->cost_price)->mul($item->qty - $item->returnedQty())
                : Money::zero();
            $cogs = $cogs->add($cost);
        }
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
        // Restock hanya item resale pada nilai modal.
        $restock = Money::zero();
        foreach ($return->items as $returnItem) {
            if ($returnItem->condition !== 'resale') {
                continue;
            }
            $saleItem = $sale->items->firstWhere('id', $returnItem->sale_item_id);
            if ($saleItem?->product !== null) {
                $restock = $restock->add(Money::fromDecimal((string) $saleItem->product->cost_price)->mul($returnItem->qty));
            }
        }
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
        $this->posting->postOnce('purchase_return', $return->id, $return->created_at ?? now(), [
            ['account' => $debitAccount, 'branch_id' => $po->branch_id, 'debit' => $total->toDecimalString(), 'memo' => $return->number],
            ['account' => Chart::PERSEDIAAN, 'branch_id' => $po->branch_id, 'credit' => $total->toDecimalString(), 'memo' => $return->number],
        ], 'Retur pembelian '.$return->number);
    }

    public function onPurchasePaymentPosted(PurchasePaymentPosted $event): void
    {
        $payment = $event->payment;
        $this->posting->postOnce('purchase_payment', $payment->id, $payment->payment_date, [
            ['account' => Chart::UTANG_SUPPLIER, 'debit' => (string) $payment->amount, 'memo' => $payment->number],
            ['account' => Chart::tenderAccount($payment->method), 'credit' => (string) $payment->amount, 'memo' => $payment->number],
        ], 'Pembayaran supplier '.$payment->number);
    }

    public function onInitialStockImported(InitialStockImported $event): void
    {
        $this->posting->postOnce('initial_stock_import', $event->batchReferenceId, now(), [
            ['account' => Chart::PERSEDIAAN, 'branch_id' => $event->branch->id, 'debit' => $event->totalValue, 'memo' => $event->branch->name],
            ['account' => Chart::MODAL_PERSEDIAAN_AWAL, 'branch_id' => $event->branch->id, 'credit' => $event->totalValue, 'memo' => $event->branch->name],
        ], 'Impor stok awal '.$event->branch->name);
    }
}
