<?php

namespace App\Modules\Lab\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Journal;
use App\Modules\Identity\Services\BranchContext;
use App\Modules\Lab\Models\LabOrder;
use App\Modules\Lab\Services\LabOrderService;
use App\Modules\Sales\Models\Sale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Order lab (F2.3, PRD Detail 5.3). Seluruh area sepagar izin
 * lab.order.manage di rute; daftar terpagar cabang aktor lewat
 * cabang penjualan induk (pola yang sama dengan antrean lab).
 */
class LabOrderController extends Controller
{
    public function __construct(private readonly LabOrderService $service) {}

    public function index(Request $request): View
    {
        $status = (string) $request->query('status', '');
        $orders = $this->scopedQuery()
            ->with(['sale.customer', 'sale.branch'])
            ->when(in_array($status, LabOrder::STATUSES, true), fn ($query) => $query->where('status', $status))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('lab.orders.index', compact('orders', 'status'));
    }

    public function create(): View
    {
        $context = BranchContext::current();
        $sales = Sale::where('status', 'active')
            ->whereHas('lensProcess')
            ->when(! $context->hasGlobalAccess(), fn ($query) => $query->whereIn('branch_id', $context->managedBranchIds()))
            ->with(['customer', 'branch'])
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return view('lab.orders.create', compact('sales'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'sale_id' => ['required', 'integer'],
            'lab_name' => ['required', 'string', 'max:100'],
            'lab_cost' => ['nullable', 'numeric', 'min:0'],
        ]);

        $sale = Sale::findOrFail((int) $validated['sale_id']);
        $this->assertSaleVisible($sale);

        $order = $this->service->create(
            $request->user(),
            $sale,
            $validated['lab_name'],
            isset($validated['lab_cost']) ? (string) $validated['lab_cost'] : '0',
        );

        return redirect()->route('lab-orders.show', $order)->with('status', __('ui.lab_orders.created'));
    }

    public function show(LabOrder $order): View
    {
        $this->assertVisible($order);
        $order->load(['sale.customer', 'sale.branch', 'creator']);
        $journal = Journal::where('source_type', 'lab_order')->where('source_id', $order->id)->first();

        return view('lab.orders.show', compact('order', 'journal'));
    }

    public function transition(Request $request, LabOrder $order): RedirectResponse
    {
        $this->assertVisible($order);
        $validated = $request->validate([
            'to' => ['required', 'string', 'in:ordered,received'],
            'lab_cost' => ['nullable', 'numeric', 'min:0'],
        ]);

        $this->service->transition(
            $request->user(),
            $order,
            $validated['to'],
            isset($validated['lab_cost']) ? (string) $validated['lab_cost'] : null,
        );

        return back()->with('status', __('ui.saved'));
    }

    private function scopedQuery()
    {
        $context = BranchContext::current();

        return LabOrder::query()->whereHas('sale', function ($query) use ($context) {
            if (! $context->hasGlobalAccess()) {
                $query->whereIn('sales.branch_id', $context->managedBranchIds());
            }
        });
    }

    private function assertVisible(LabOrder $order): void
    {
        $this->assertSaleVisible($order->sale()->withoutGlobalScopes()->firstOrFail());
    }

    private function assertSaleVisible(Sale $sale): void
    {
        $context = BranchContext::current();
        abort_unless($context->allowsBranch((int) $sale->branch_id), 404);
    }
}
