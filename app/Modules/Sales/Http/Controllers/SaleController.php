<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Product;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Services\BranchContext;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Doctor;
use App\Modules\Master\Models\Insurance;
use App\Modules\Master\Models\Setting;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SalePayment;
use App\Modules\Sales\Services\SaleReturnService;
use App\Modules\Sales\Services\SalesService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Penjualan (Blueprint D.6 + PRD Detail 2.1–2.5).
 * Detail = halaman penuh; dokumen lewat satu DropdownCetak;
 * perintah lewat satu DropdownAksi (kontrak UI).
 */
class SaleController extends Controller
{
    public function __construct(
        private readonly SalesService $service,
        private readonly SaleReturnService $returns,
    ) {}

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $branchId = $request->query('cabang');
        $payStatus = (string) $request->query('status_bayar', '');

        $sales = Sale::with(['customer', 'branch'])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('number', 'like', "%{$q}%")
                        ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$q}%"));
                });
            })
            ->when($branchId, fn ($query) => $query->where('branch_id', (int) $branchId))
            ->when($payStatus !== '', function ($query) use ($payStatus) {
                match ($payStatus) {
                    'paid' => $query->where('status', 'active')->whereColumn('paid_amount', '>=', 'total_amount'),
                    'partial' => $query->where('status', 'active')->where('paid_amount', '>', 0)->whereColumn('paid_amount', '<', 'total_amount'),
                    'unpaid' => $query->where('status', 'active')->where('paid_amount', '<=', 0),
                    'void' => $query->where('status', 'void'),
                    default => $query,
                };
            })
            ->orderByDesc('sale_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $context = BranchContext::current();
        $branches = $context->hasGlobalAccess()
            ? Branch::orderBy('name')->get()
            : Branch::whereIn('id', $context->managedBranchIds())->orderBy('name')->get();

        return view('sales.index', compact('sales', 'q', 'branchId', 'payStatus', 'branches'));
    }

    public function show(Sale $sale): View
    {
        $sale->load(['customer', 'branch', 'cashier', 'doctor', 'insurance', 'items', 'payments.receivedBy', 'returns.items']);

        return view('sales.show', compact('sale'));
    }

    public function create(Request $request): View
    {
        $customers = Customer::where('is_active', true)->orderBy('name')->get();
        $customerId = (int) $request->query('pelanggan', 0);
        $selectedCustomer = $customers->firstWhere('id', $customerId)
            ?? ($customers->count() === 1 ? $customers->first() : null);

        $prescriptions = $selectedCustomer !== null
            ? $selectedCustomer->prescriptions()->with('examiner')->get()
            : collect();

        return view('sales.create', [
            'customers' => $customers,
            'selectedCustomer' => $selectedCustomer,
            'prescriptions' => $prescriptions,
            'products' => Product::where('is_active', true)->orderBy('type')->orderBy('name')->get(),
            'doctors' => Doctor::where('is_active', true)->orderBy('name')->get(),
            'insurances' => Insurance::where('is_active', true)->orderBy('name')->get(),
            'taxMode' => (string) Setting::value('tax.mode', 'inclusive'),
            'taxRate' => (string) Setting::value('tax.rate_default', '11'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        // Baris item kosong dari formulir dibuang sebelum validasi.
        $items = collect((array) $request->input('items', []))
            ->filter(fn ($row) => is_array($row) && ! empty($row['product_id']) && ! empty($row['qty']))
            ->values()
            ->all();
        $request->merge(['items' => $items]);

        $validated = $request->validate([
            'customer_id' => ['required', Rule::exists('customers', 'id')],
            'prescription_id' => ['nullable', Rule::exists('prescriptions', 'id')],
            'sale_date' => ['required', 'date'],
            'doctor_id' => ['nullable', Rule::exists('doctors', 'id')],
            'insurance_id' => ['nullable', Rule::exists('insurances', 'id')],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'tax_mode' => ['required', Rule::in(['inclusive', 'exclusive'])],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', Rule::exists('products', 'id')],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.prescription_ref' => ['nullable', Rule::in(['od', 'os', 'pair'])],
            'payment_method' => ['nullable', Rule::in(SalePayment::METHODS)],
            'payment_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_reference' => ['nullable', 'string', 'max:80'],
        ]);

        $payments = [];
        if (! empty($validated['payment_method']) && ! empty($validated['payment_amount'])) {
            $payments[] = [
                'method' => $validated['payment_method'],
                'amount' => $validated['payment_amount'],
                'reference' => $validated['payment_reference'] ?? null,
            ];
        }

        $sale = $this->service->createSale(
            $request->user(),
            collect($validated)->only(['customer_id', 'prescription_id', 'sale_date', 'doctor_id', 'insurance_id', 'discount_amount', 'tax_mode', 'notes'])->all(),
            $validated['items'],
            $payments,
        );

        return redirect()->route('sales.show', $sale)->with('status', __('ui.sales.created'));
    }

    public function paymentForm(Sale $sale): View
    {
        $sale->load('customer');

        return view('sales.payment', compact('sale'));
    }

    public function storePayment(Request $request, Sale $sale): RedirectResponse
    {
        $validated = $request->validate([
            'method' => ['required', Rule::in(SalePayment::METHODS)],
            'amount' => ['required', 'numeric', 'min:0'],
            'reference' => ['nullable', 'string', 'max:80'],
        ]);

        $this->service->addPayment($request->user(), $sale, $validated['method'], (string) $validated['amount'], $validated['reference'] ?? null);

        return redirect()->route('sales.show', $sale)->with('status', __('ui.sales.payment_added'));
    }

    public function voidForm(Sale $sale): View
    {
        $sale->load('customer');

        return view('sales.void', compact('sale'));
    }

    public function storeVoid(Request $request, Sale $sale): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $this->service->voidSale($request->user(), $sale, $validated['reason']);

        return redirect()->route('sales.show', $sale)->with('status', __('ui.sales.void_done'));
    }

    public function returnForm(Sale $sale): View
    {
        $sale->load(['customer', 'items.returnItems']);

        return view('sales.return', compact('sale'));
    }

    public function storeReturn(Request $request, Sale $sale): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
            'refund_method' => ['required', Rule::in(['cash', 'transfer', 'store_credit'])],
            'items' => ['required', 'array'],
            'items.*.sale_item_id' => ['required', 'integer'],
            'items.*.qty' => ['nullable', 'integer', 'min:0'],
            'items.*.condition' => ['required', Rule::in(['resale', 'damaged'])],
        ]);

        $lines = collect($validated['items'])
            ->filter(fn ($line) => (int) ($line['qty'] ?? 0) > 0)
            ->values()
            ->all();

        $return = $this->returns->create($request->user(), $sale, $lines, $validated['reason'], $validated['refund_method']);

        return redirect()->route('sales.show', $sale)->with('status', __('ui.sales.return_created').' '.$return->number);
    }

    // ---- Dokumen cetak (tab baru, pola F.2) ----

    public function printOrder(Sale $sale): View
    {
        $sale->load(['customer', 'branch', 'cashier', 'items']);

        return view('sales.print-order', [
            'sale' => $sale,
            'storeName' => Setting::value('store.name', 'Lentera'),
        ]);
    }

    public function printInvoice(Sale $sale): View
    {
        $sale->load(['customer', 'branch', 'cashier', 'items', 'payments']);

        return view('sales.print-invoice', [
            'sale' => $sale,
            'storeName' => Setting::value('store.name', 'Lentera'),
        ]);
    }

    public function printPrescription(Sale $sale): View
    {
        $sale->load(['customer', 'branch']);

        // Dokumen resep dari penjualan mencetak SNAPSHOT transaksi.
        return view('sales.print-prescription', [
            'sale' => $sale,
            'snapshot' => $sale->prescription_snapshot,
            'storeName' => Setting::value('store.name', 'Lentera'),
        ]);
    }
}
