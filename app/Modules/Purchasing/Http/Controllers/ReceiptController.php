<?php

namespace App\Modules\Purchasing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Services\BranchContext;
use App\Modules\Purchasing\Models\PurchasePayment;
use App\Modules\Purchasing\Models\Receipt;
use App\Modules\Purchasing\Services\PurchasePaymentService;
use App\Modules\Purchasing\Services\PurchaseReturnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Detail receipt (halaman penuh): item diterima, pembayaran teralokasi,
 * retur, dan formulir bayar tunggal + retur (PRD 6.3/6.4/6.6).
 * Pagar cabang lewat PO induk.
 */
class ReceiptController extends Controller
{
    public function __construct(
        private readonly PurchasePaymentService $payments,
        private readonly PurchaseReturnService $returns,
    ) {}

    public function show(Receipt $receipt): View
    {
        $this->assertVisible($receipt);
        $receipt->load([
            'purchaseOrder.supplier', 'purchaseOrder.branch', 'receiver',
            'items.purchaseOrderItem.product', 'items.returnItems',
            'allocations.payment', 'returns.items',
        ]);

        return view('purchasing.receipts.show', compact('receipt'));
    }

    public function payForm(Receipt $receipt): View
    {
        $this->assertVisible($receipt);
        $receipt->load(['purchaseOrder.supplier', 'items', 'allocations', 'returns']);

        return view('purchasing.receipts.pay', compact('receipt'));
    }

    public function storePayment(Request $request, Receipt $receipt): RedirectResponse
    {
        $this->assertVisible($receipt);
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
            'method' => ['required', 'in:'.implode(',', PurchasePayment::METHODS)],
            'payment_date' => ['nullable', 'date'],
            'reference' => ['nullable', 'string', 'max:80'],
        ]);

        $this->payments->paySingle(
            $request->user(), $receipt,
            (string) $validated['amount'], $validated['method'],
            $validated['payment_date'] ?? null, $validated['reference'] ?? null,
        );

        return redirect()->route('receipts.show', $receipt)->with('status', __('ui.saved'));
    }

    public function returnForm(Receipt $receipt): View
    {
        $this->assertVisible($receipt);
        $receipt->load(['purchaseOrder.supplier', 'items.purchaseOrderItem.product', 'items.returnItems']);

        return view('purchasing.receipts.return', compact('receipt'));
    }

    public function storeReturn(Request $request, Receipt $receipt): RedirectResponse
    {
        $this->assertVisible($receipt);
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
            'items' => ['required', 'array'],
            'items.*.receipt_item_id' => ['required', 'integer'],
            'items.*.qty' => ['nullable', 'integer', 'min:0'],
        ]);

        $retur = $this->returns->store(
            $request->user(), $receipt,
            collect($validated['items'])->map(fn ($row) => [
                'receipt_item_id' => (int) $row['receipt_item_id'],
                'qty' => (int) ($row['qty'] ?? 0),
            ])->all(),
            $validated['reason'],
        );

        return redirect()->route('receipts.show', $receipt)
            ->with('status', __('ui.saved').' — '.$retur->number);
    }

    private function assertVisible(Receipt $receipt): void
    {
        $context = BranchContext::current();
        $branchId = $receipt->purchaseOrder()->withoutGlobalScopes()->value('branch_id');
        abort_unless($branchId !== null && $context->allowsBranch((int) $branchId), 404);
    }
}
