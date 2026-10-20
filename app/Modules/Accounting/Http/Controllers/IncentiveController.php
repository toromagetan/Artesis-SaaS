<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\IncentivePeriod;
use App\Modules\Accounting\Models\IncentiveRule;
use App\Modules\Accounting\Services\IncentiveService;
use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\BranchContext;
use App\Modules\Master\Models\Branch;
use App\Support\XlsxWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Insentif berkala (paritas Artesis IncentiveController): aturan,
 * generate periode per bulan kalender, detail, setujui, tandai
 * dibayar, ekspor Excel per periode. Sepagar payroll.manage;
 * pagar cabang lewat BranchContext.
 */
class IncentiveController extends Controller
{
    public function index(Request $request): View
    {
        $context = BranchContext::for($request->user());
        $periods = IncentivePeriod::with(['branch', 'recipient'])
            ->when(! $context->hasGlobalAccess(), fn ($q) => $q->whereIn('branch_id', $context->managedBranchIds()))
            ->latest('id')
            ->paginate(20);

        return view('accounting.incentives.index', [
            'periods' => $periods,
            'rules' => IncentiveRule::with('product')->latest('id')->get(),
            'branches' => Branch::where('is_active', true)->orderBy('name')->get(),
            'roles' => Role::orderBy('name')->pluck('name'),
            'products' => Product::where('is_active', true)->orderBy('name')->get(['id', 'sku', 'name']),
            'recipients' => User::where('is_active', true)
                ->whereHas('roles', fn ($q) => $q->whereIn('name', IncentiveService::ELIGIBLE_ROLES))
                ->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function storeRule(Request $request, IncentiveService $service): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'recipient_role' => ['required', Rule::exists('roles', 'name')],
            'product_id' => ['nullable', Rule::exists('products', 'id')],
            'calculation_type' => ['required', Rule::in(['percent', 'flat'])],
            'value' => ['required', 'numeric', 'gt:0'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ]);
        $service->createRule($request->user(), $validated);

        return back()->with('status', __('ui.incentives.rule_created'));
    }

    public function toggleRule(Request $request, IncentiveRule $rule, IncentiveService $service): RedirectResponse
    {
        $service->toggleRule($request->user(), $rule);

        return back()->with('status', __('ui.incentives.rule_toggled'));
    }

    public function generate(Request $request, IncentiveService $service): RedirectResponse
    {
        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'branch_id' => ['required', Rule::exists('branches', 'id')],
            'user_id' => ['nullable', Rule::exists('users', 'id')],
        ]);
        $context = BranchContext::for($request->user());
        $context->assertCanAccess((int) $validated['branch_id']);

        $recipientId = null;
        if (! empty($validated['user_id'])) {
            $recipient = User::where('is_active', true)
                ->whereHas('roles', fn ($q) => $q->whereIn('name', IncentiveService::ELIGIBLE_ROLES))
                ->find((int) $validated['user_id']);
            if ($recipient === null) {
                return back()->withErrors(['user_id' => __('ui.incentives.recipient_invalid')])->withInput();
            }
            if (! $recipient->branches->contains((int) $validated['branch_id'])) {
                return back()->withErrors(['user_id' => __('ui.incentives.recipient_branch_mismatch')])->withInput();
            }
            $recipientId = $recipient->id;
        }

        $actor = $request->user();
        $branchId = (int) $validated['branch_id'];
        $service->generate($actor, $branchId, $validated['month'], $recipientId);

        return back()->with('status', __('ui.incentives.generated'));
    }

    public function show(Request $request, IncentivePeriod $period): View
    {
        $context = BranchContext::for($request->user());
        if ($period->branch_id !== null) {
            $context->assertCanAccess($period->branch_id);
        }
        $period->load(['branch', 'recipient', 'generator', 'approver', 'payer', 'items.recipient', 'items.rule', 'items.sale', 'adjustments.recipient']);

        return view('accounting.incentives.show', ['period' => $period]);
    }

    public function approve(Request $request, IncentivePeriod $period, IncentiveService $service): RedirectResponse
    {
        $context = BranchContext::for($request->user());
        if ($period->branch_id !== null) {
            $context->assertCanAccess($period->branch_id);
        }
        $service->approve($request->user(), $period);

        return back()->with('status', __('ui.incentives.approved'));
    }

    public function pay(Request $request, IncentivePeriod $period, IncentiveService $service): RedirectResponse
    {
        $context = BranchContext::for($request->user());
        if ($period->branch_id !== null) {
            $context->assertCanAccess($period->branch_id);
        }
        $service->markPaid($request->user(), $period);

        return back()->with('status', __('ui.incentives.paid'));
    }

    /** Ekspor Excel satu periode (paritas Artesis): item + penyesuaian + neto. */
    public function export(Request $request, IncentivePeriod $period): StreamedResponse
    {
        $context = BranchContext::for($request->user());
        if ($period->branch_id !== null) {
            $context->assertCanAccess($period->branch_id);
        }
        $period->load(['items.recipient', 'items.sale', 'adjustments.recipient']);

        $rows = [
            ['Penerima', 'Peran', 'Penjualan', 'Produk', 'Qty', 'Basis Item', 'Total Transaksi', 'Tipe', 'Nilai Rule', 'Insentif', 'Status', 'Alasan Batal'],
            ...$period->items->map(fn ($i) => [
                $i->recipient?->name, $i->recipient_role, $i->sale?->number, $i->product_name,
                $i->quantity, (string) $i->item_final_amount, (string) $i->transaction_amount,
                $i->calculation_type, (string) $i->rule_value, (string) $i->incentive_amount,
                $i->status, $i->cancellation_reason,
            ])->all(),
            ['TOTAL ITEM', '', '', '', '', '', '', '', '', (string) $period->incentive_total, '', ''],
        ];
        if ($period->adjustments->isNotEmpty()) {
            $rows[] = ['PENYESUAIAN (CLAWBACK RETUR)'];
            $rows[] = ['Penerima', 'Peran', 'Produk', 'Qty Retur', 'Jumlah', 'Alasan'];
            foreach ($period->adjustments as $a) {
                $rows[] = [$a->recipient?->name, $a->recipient_role, $a->product_name, $a->returned_quantity, (string) $a->amount, $a->reason];
            }
            $rows[] = ['TOTAL PENYESUAIAN', '', '', '', $period->adjustmentsTotal()->toDecimalString(), ''];
        }
        $rows[] = ['NETO', '', '', '', '', $period->netTotal()->toDecimalString(), '', '', '', '', '', ''];

        $bytes = XlsxWriter::make([['title' => 'insentif-'.$period->period_start->format('Y-m'), 'rows' => $rows]]);
        $filename = 'insentif-'.$period->period_start->format('Y-m').'-'.$period->id.'.xlsx';

        return response()->streamDownload(function () use ($bytes) {
            echo $bytes;
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
