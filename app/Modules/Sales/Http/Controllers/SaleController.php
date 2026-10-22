<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Product;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Services\BranchContext;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Lab\Services\ProcurementStatusService;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Doctor;
use App\Modules\Master\Models\Insurance;
use App\Modules\Master\Models\Setting;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleItem;
use App\Modules\Sales\Models\SalePayment;
use App\Modules\Sales\Services\InstallmentService;
use App\Modules\Sales\Services\SaleReturnService;
use App\Modules\Sales\Services\SalesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Penjualan (Blueprint D.6 + PRD Detail 2.1–2.5).
 * Detail = halaman penuh; dokumen & perintah sebagai tombol
 * sejajar AksiBar (ADR-018).
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
        $sale->load(['customer', 'branch', 'cashier', 'doctor', 'insurance', 'items', 'payments.receivedBy', 'returns.items', 'installments']);

        // Status proses lensa TURUNAN dari rantai PR->PO (paritas Artesis).
        $procurementStage = app(ProcurementStatusService::class)->stageFor($sale);
        $linkedPrs = $procurementStage !== null
            ? $sale->purchaseRequests()->orderBy('id')->get()
            : collect();

        return view('sales.show', compact('sale', 'procurementStage', 'linkedPrs'));
    }

    public function create(Request $request): View
    {
        // Terminal POS satu halaman (ADR-019): pelanggan & produk
        // dicari async lewat endpoint JSON; halaman hanya memuat
        // dokter, asuransi, bawaan pajak, dan pelanggan terpilih awal
        // (tautan "?pelanggan=" dari detail pelanggan/penjualan).
        $selectedCustomer = null;
        $prescriptions = collect();
        $customerId = (int) $request->query('pelanggan', 0);
        if ($customerId > 0) {
            $selectedCustomer = Customer::where('is_active', true)->find($customerId);
            if ($selectedCustomer !== null) {
                $prescriptions = $selectedCustomer->prescriptions()->with('examiner')->get();
            }
        }

        return view('sales.create', [
            'selectedCustomer' => $selectedCustomer,
            'customerData' => $selectedCustomer !== null ? [
                'id' => $selectedCustomer->id,
                'name' => $selectedCustomer->name,
                'phone' => $selectedCustomer->phone,
            ] : null,
            'prescriptionData' => $prescriptions->map(fn ($rx) => $this->prescriptionPayload($rx))->values(),
            'doctors' => Doctor::where('is_active', true)->orderBy('name')->get(),
            'insurances' => Insurance::where('is_active', true)->orderBy('name')->get(),
            'taxMode' => (string) Setting::value('tax.mode', 'inclusive'),
            'taxRate' => (string) Setting::value('tax.rate_default', '11'),
        ]);
    }

    /** Pencarian produk async untuk terminal POS (ADR-019). */
    public function searchProducts(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $branchId = BranchContext::for($request->user())->currentBranchId();
        $inventory = app(InventoryService::class);

        $products = Product::where('is_active', true)
            ->with('parent')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('name', 'like', "%{$q}%")
                        ->orWhere('sku', 'like', "%{$q}%");
                });
            })
            ->orderBy('type')
            ->orderBy('name')
            ->limit(15)
            ->get();

        return response()->json($products->map(fn (Product $product) => [
            'id' => $product->id,
            'name' => $product->name,
            'parent_name' => $product->parent?->name,
            'sku' => $product->sku,
            'type' => $product->type,
            'type_label' => $product->typeLabel(),
            'sale_price' => (string) $product->sale_price,
            'stock' => $branchId !== null ? $inventory->currentStock($product->id, $branchId) : null,
        ])->values());
    }

    /** Pencarian pelanggan async untuk terminal POS (ADR-019). */
    public function searchCustomers(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        $customers = Customer::where('is_active', true)
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('name', 'like', "%{$q}%")
                        ->orWhere('phone', 'like', "%{$q}%");
                });
            })
            ->orderBy('name')
            ->limit(15)
            ->get();

        return response()->json($customers->map(fn (Customer $customer) => [
            'id' => $customer->id,
            'name' => $customer->name,
            'phone' => $customer->phone,
        ])->values());
    }

    /** Resep milik pelanggan untuk pemilih resep terminal (ADR-019). */
    public function customerPrescriptions(Customer $customer): JsonResponse
    {
        $prescriptions = $customer->prescriptions()->with('examiner')->get();

        return response()->json($prescriptions->map(fn ($rx) => $this->prescriptionPayload($rx))->values());
    }

    /** Bentuk payload resep untuk terminal POS (pemilih + rekap). */
    private function prescriptionPayload($rx): array
    {
        return [
            'id' => $rx->id,
            'label' => $rx->examined_at->format('d/m/Y').' — OD '.$rx->od_sph.'/'.$rx->od_cyl.' · OS '.$rx->os_sph.'/'.$rx->os_cyl,
            'examined_at' => $rx->examined_at->format('Y-m-d'),
            'od' => $rx->eye('od'),
            'os' => $rx->eye('os'),
            'pd_total' => $rx->pd_total !== null ? (string) $rx->pd_total : null,
        ];
    }

    /** Lengkapi/ubah precal manual item RX dari detail penjualan (ADR-019). */
    public function updatePrecal(Request $request, Sale $sale, SaleItem $item): RedirectResponse
    {
        abort_unless($item->sale_id === $sale->id, 404);

        $this->service->updateItemPrecal(
            $request->user(),
            $item,
            (array) $request->input('precal', []),
        );

        return redirect()->route('sales.show', $sale)->with('status', __('ui.sales.precal_updated'));
    }

    public function store(Request $request): RedirectResponse
    {
        // Baris item kosong dari formulir dibuang sebelum validasi.
        $items = collect((array) $request->input('items', []))
            ->filter(fn ($row) => is_array($row) && ! empty($row['product_id']) && ! empty($row['qty']))
            ->values()
            ->all();
        $request->merge(['items' => $items]);

        // Baris pembayaran split (F3.1): kosong dibuang sebelum validasi.
        $paymentRows = collect((array) $request->input('payments', []))
            ->filter(fn ($row) => is_array($row) && ! empty($row['method']) && ! empty($row['amount']))
            ->values()
            ->all();
        $request->merge(['payments' => $paymentRows]);

        $validated = $request->validate([
            'customer_id' => ['required', Rule::exists('customers', 'id')],
            'prescription_id' => ['nullable', Rule::exists('prescriptions', 'id')],
            'sale_date' => ['required', 'date'],
            'doctor_id' => ['nullable', Rule::exists('doctors', 'id')],
            'insurance_id' => ['nullable', Rule::exists('insurances', 'id')],
            'insurance_claim_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'tax_mode' => ['required', Rule::in(['inclusive', 'exclusive'])],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', Rule::exists('products', 'id')],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.prescription_ref' => ['nullable', Rule::in(['od', 'os', 'pair'])],
            // Precal manual (ADR-019): bentuk saja yang diperiksa di
            // sini; validasi 11 kunci oleh RxPrecalValidator di service.
            'items.*.precal' => ['nullable', 'array'],
            'payment_method' => ['nullable', Rule::in(SalePayment::METHODS)],
            'payment_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_reference' => ['nullable', 'string', 'max:80'],
            'payments' => ['nullable', 'array'],
            'payments.*.method' => ['required', Rule::in(SalePayment::METHODS)],
            'payments.*.amount' => ['required', 'numeric', 'gt:0'],
            'payments.*.reference' => ['nullable', 'string', 'max:80'],
            'installment_count' => ['nullable', 'integer', 'min:1', 'max:36'],
            'installment_first_due' => ['nullable', 'date'],
            'installment_interval_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        // Split payment (F3.1): banyak baris metode; kolom tunggal lama
        // tetap diterima sebagai cadangan satu baris.
        $payments = [];
        foreach ($validated['payments'] ?? [] as $row) {
            $payments[] = [
                'method' => $row['method'],
                'amount' => $row['amount'],
                'reference' => $row['reference'] ?? null,
            ];
        }
        if ($payments === [] && ! empty($validated['payment_method']) && ! empty($validated['payment_amount'])) {
            $payments[] = [
                'method' => $validated['payment_method'],
                'amount' => $validated['payment_amount'],
                'reference' => $validated['payment_reference'] ?? null,
            ];
        }

        $sale = $this->service->createSale(
            $request->user(),
            collect($validated)->only(['customer_id', 'prescription_id', 'sale_date', 'doctor_id', 'insurance_id', 'insurance_claim_amount', 'discount_amount', 'tax_mode', 'notes', 'installment_count', 'installment_first_due', 'installment_interval_days'])->all(),
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

    /** Formulir jadwal cicilan atas sisa piutang (F3.2). */
    public function installmentForm(Sale $sale): View
    {
        $sale->load(['customer', 'installments']);

        return view('sales.installment-form', compact('sale'));
    }

    public function storeInstallment(Request $request, Sale $sale): RedirectResponse
    {
        $validated = $request->validate([
            'installment_count' => ['required', 'integer', 'min:1', 'max:36'],
            'installment_first_due' => ['required', 'date'],
            'installment_interval_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        app(InstallmentService::class)->createSchedule(
            $request->user(),
            $sale,
            (int) $validated['installment_count'],
            (string) $validated['installment_first_due'],
            (int) ($validated['installment_interval_days'] ?? 30),
        );

        return redirect()->route('sales.show', $sale)->with('status', __('ui.sales.schedule_created'));
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
