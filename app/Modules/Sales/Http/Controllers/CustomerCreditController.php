<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Sales\Models\CustomerCredit;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Services\CustomerCreditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Penerapan voucher kredit pelanggan ke penjualan (paritas
 * Artesis POST /credits/{credit}/apply). Kredit dan penjualan
 * terpagar cabang lewat BranchScoped pada model.
 */
class CustomerCreditController extends Controller
{
    public function applyForm(CustomerCredit $credit): View
    {
        $credit->load(['customer', 'sale', 'saleReturn']);

        $sales = Sale::where('customer_id', $credit->customer_id)
            ->where('status', '!=', 'void')
            ->orderByDesc('sale_date')
            ->orderByDesc('id')
            ->get()
            ->filter(fn (Sale $sale) => $sale->remaining()->isPositive()
                && ! $sale->creditApplications()->where('customer_credit_id', $credit->id)->exists())
            ->values();

        return view('sales.credits.apply', compact('credit', 'sales'));
    }

    public function apply(Request $request, CustomerCredit $credit, CustomerCreditService $service): RedirectResponse
    {
        $validated = $request->validate([
            'sale_id' => ['required', Rule::exists('sales', 'id')],
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $sale = Sale::findOrFail($validated['sale_id']);
        $service->apply($request->user(), $credit, $sale, (string) $validated['amount']);

        return redirect()->route('sales.show', $sale)
            ->with('status', __('ui.credits.applied'));
    }
}
