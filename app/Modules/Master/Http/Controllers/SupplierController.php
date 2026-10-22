<?php

namespace App\Modules\Master\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Services\BranchContext;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Supplier;
use App\Modules\Platform\Services\AuditLogger;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Models\PurchasePayment;
use App\Modules\Reporting\Services\ReportService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Master supplier (PRD-Detail 6.7): daftar (nama, kontak, alamat,
 * jumlah PO, total utang berjalan, status aktif) + detail halaman
 * penuh (riwayat PO & pembayaran, ringkasan utang, receipt terbuka).
 *
 * Master bersifat GLOBAL (ADR-012 — tabel suppliers tanpa branch_id);
 * seluruh angka transaksional (PO, utang, pembayaran) terpagar cabang
 * aktor lewat BranchContext, sama seperti ReportService::supplierPayables.
 * Tanpa hapus — nonaktif saja (aturan rumah tanpa-hapus).
 */
class SupplierController extends Controller
{
    public function __construct(private readonly ReportService $reports) {}

    /** @return int[] */
    private function visibleBranchIds(): array
    {
        $context = BranchContext::current();

        return $context->hasGlobalAccess()
            ? Branch::query()->pluck('id')->map(fn ($id) => (int) $id)->all()
            : $context->managedBranchIds();
    }

    /**
     * Ringkasan utang per NAMA supplier (kontrak ReportService yang ada).
     *
     * @param  int[]  $branchIds
     * @return array<string, array{total: Money, credit: Money, receipts: array<int, array<string, mixed>>}>
     */
    private function payablesByName(array $branchIds): array
    {
        $map = [];
        foreach ($this->reports->supplierPayables($branchIds)['suppliers'] as $row) {
            $map[$row['supplier_name']] = $row;
        }

        return $map;
    }

    public function index(): View
    {
        $branchIds = $this->visibleBranchIds();
        $poCounts = PurchaseOrder::query()
            ->whereIn('branch_id', $branchIds)
            ->where('status', '!=', 'void')
            ->selectRaw('supplier_id, COUNT(*) as total')
            ->groupBy('supplier_id')
            ->pluck('total', 'supplier_id');
        $payables = $this->payablesByName($branchIds);

        $suppliers = Supplier::query()->orderBy('name')->paginate(20)
            ->through(function (Supplier $supplier) use ($poCounts, $payables) {
                $supplier->setAttribute('po_count', (int) ($poCounts[$supplier->id] ?? 0));
                $supplier->setAttribute('outstanding', $payables[$supplier->name]['total'] ?? Money::zero());

                return $supplier;
            });

        return view('master.suppliers.index', compact('suppliers'));
    }

    public function create(): View
    {
        return view('master.suppliers.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $supplier = Supplier::create($data + ['is_active' => true]);
        app(AuditLogger::class)->log(
            action: 'supplier.created',
            subjectType: 'supplier',
            subjectId: $supplier->id,
            newValues: ['name' => $supplier->name],
            actor: $request->user(),
        );

        return redirect()->route('suppliers.index')->with('status', __('ui.suppliers.created'));
    }

    public function show(Supplier $supplier): View
    {
        $branchIds = $this->visibleBranchIds();
        $payable = $this->payablesByName($branchIds)[$supplier->name] ?? null;

        $orders = PurchaseOrder::query()
            ->where('supplier_id', $supplier->id)
            ->whereIn('branch_id', $branchIds)
            ->with('branch')
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->paginate(10, ['*'], 'po_page');

        $payments = PurchasePayment::query()
            ->join('purchase_payment_allocations as alloc', 'alloc.purchase_payment_id', '=', 'purchase_payments.id')
            ->join('receipts', 'receipts.id', '=', 'alloc.receipt_id')
            ->join('purchase_orders as po', 'po.id', '=', 'receipts.purchase_order_id')
            ->where('purchase_payments.supplier_id', $supplier->id)
            ->whereIn('po.branch_id', $branchIds)
            ->select('purchase_payments.*')
            ->distinct()
            ->orderByDesc('purchase_payments.payment_date')
            ->orderByDesc('purchase_payments.id')
            ->paginate(10, ['*'], 'pay_page');

        return view('master.suppliers.show', [
            'supplier' => $supplier,
            'orders' => $orders,
            'payments' => $payments,
            'openReceipts' => $payable['receipts'] ?? [],
            'outstanding' => $payable['total'] ?? Money::zero(),
            'credit' => $payable['credit'] ?? Money::zero(),
            'poCount' => $orders->total(),
        ]);
    }

    public function edit(Supplier $supplier): View
    {
        return view('master.suppliers.edit', compact('supplier'));
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($this->validated($request));
        app(AuditLogger::class)->log(
            action: 'supplier.updated',
            subjectType: 'supplier',
            subjectId: $supplier->id,
            newValues: ['name' => $supplier->name],
            actor: $request->user(),
        );

        return redirect()->route('suppliers.index')->with('status', __('ui.suppliers.updated'));
    }

    public function toggleActive(Request $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update(['is_active' => ! $supplier->is_active]);
        app(AuditLogger::class)->log(
            action: 'supplier.toggled',
            subjectType: 'supplier',
            subjectId: $supplier->id,
            newValues: ['is_active' => $supplier->is_active],
            actor: $request->user(),
        );

        return redirect()->route('suppliers.index')
            ->with('status', $supplier->is_active ? __('ui.suppliers.activated') : __('ui.suppliers.deactivated'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'contact_person' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string'],
        ]);
    }
}
