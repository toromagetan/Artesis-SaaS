<?php

namespace App\Modules\Purchasing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Master\Models\Setting;
use App\Modules\Master\Models\Supplier;
use App\Modules\Purchasing\Models\PurchasePayment;
use App\Modules\Purchasing\Services\CollectivePaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Pembayaran kolektif supplier (PRD 6.5): pilih supplier → daftar
 * receipt belum lunas FIFO → satu jumlah bayar → alokasi FIFO dalam
 * SATU transaksi. Kegagalan apa pun menjadi pesan ramah, TIDAK PERNAH
 * halaman error mentah (kontrak D.7 butir 4).
 */
#[Middleware('plan.feature:purchasing_full')]
class CollectivePaymentController extends Controller
{
    public function __construct(private readonly CollectivePaymentService $collective) {}

    public function form(Request $request): View
    {
        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get();
        $supplier = null;
        $outstanding = [];
        $totalOutstanding = null;

        $supplierId = $request->query('supplier');
        if ($supplierId) {
            $supplier = Supplier::where('is_active', true)->find((int) $supplierId);
            if ($supplier !== null) {
                $outstanding = $this->collective->outstandingReceipts($supplier);
                $totalOutstanding = $this->collective->totalOutstanding($supplier);
            }
        }

        return view('purchasing.collective.form', compact('suppliers', 'supplier', 'outstanding', 'totalOutstanding'));
    }

    public function process(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'supplier_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'min:0'],
            'method' => ['required', 'in:'.implode(',', PurchasePayment::METHODS)],
            'payment_date' => ['nullable', 'date'],
            'reference' => ['nullable', 'string', 'max:80'],
        ]);

        $supplier = Supplier::findOrFail((int) $validated['supplier_id']);

        try {
            $payment = $this->collective->process(
                $request->user(), $supplier,
                (string) $validated['amount'], $validated['method'],
                $validated['payment_date'] ?? null, $validated['reference'] ?? null,
            );
        } catch (\Throwable $e) {
            // Kontrak D.7: error apa pun → pesan ramah, bukan 500 mentah.
            Log::warning('Collective payment failed', ['error' => $e->getMessage()]);
            $message = $e instanceof ValidationException
                ? collect($e->errors())->flatten()->first()
                : __('ui.purchasing.collective_failed');

            return back()->withInput()->withErrors(['amount' => $message]);
        }

        return redirect()->route('payments.collective.result', $payment)
            ->with('status', __('ui.saved'));
    }

    /** Kuitansi pembayaran supplier (audit 23 Okt 2026 B4). */
    public function printPayment(PurchasePayment $payment): View
    {
        $payment->load(['supplier', 'allocations.receipt.purchaseOrder', 'payer']);

        return view('purchasing.payments.print', [
            'payment' => $payment,
            'storeName' => Setting::value('store.name', 'Artesis'),
            'printedBy' => auth()->user(),
        ]);
    }

    public function result(PurchasePayment $payment): View
    {
        $payment->load(['supplier', 'payer', 'allocations.receipt.purchaseOrder']);

        return view('purchasing.collective.result', compact('payment'));
    }
}
