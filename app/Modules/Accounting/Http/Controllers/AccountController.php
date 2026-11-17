<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Services\AccountService;
use App\Modules\Identity\Services\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Bagan akun (F1.1): baca untuk accounts.view; kelola (buat/ubah/
 * nonaktif — tanpa hapus) untuk accounts.manage.
 */
class AccountController extends Controller
{
    public function __construct(private readonly AccountService $service) {}

    public function index(): View
    {
        $accounts = Account::with('parent')->orderBy('code')->get();

        return view('accounting.accounts.index', compact('accounts'));
    }

    public function show(Account $account): View
    {
        $account->load(['parent', 'children']);
        // Bagan akun bersifat global (referensi bersama), tetapi baris
        // jurnalnya data per tenant: agregat dan baris terbaru WAJIB
        // terpagar ember aktor lewat jurnal induknya — tanpa pagar ini
        // total menjumlah semua tenant dan baris asing membuat relasi
        // journal null di view (audit 17 Nov 2026 F-3).
        $context = TenantContext::current();
        $lines = fn () => $account->lines()
            ->whereHas('journal', fn ($q) => $context->constrainByTenant($q));
        $totals = $lines()
            ->selectRaw('COALESCE(SUM(debit),0) as total_debit, COALESCE(SUM(credit),0) as total_credit, COUNT(*) as line_count')
            ->first();
        $recentLines = $lines()->with('journal')->orderByDesc('id')->limit(10)->get();

        return view('accounting.accounts.show', compact('account', 'totals', 'recentLines'));
    }

    public function create(): View
    {
        return view('accounting.accounts.create', ['parents' => $this->parentOptions()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('accounts', 'code')],
            'name' => ['required', 'string', 'max:150'],
            'group' => ['required', Rule::in(Account::GROUPS)],
            'parent_id' => ['nullable', Rule::exists('accounts', 'id')],
        ]);

        $account = $this->service->create($request->user(), $validated);

        return redirect()->route('accounts.show', $account)->with('status', __('ui.accounting.account_created'));
    }

    public function edit(Account $account): View
    {
        return view('accounting.accounts.edit', [
            'account' => $account,
            'parents' => $this->parentOptions($account),
            'hasLines' => $account->lines()->exists(),
        ]);
    }

    public function update(Request $request, Account $account): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('accounts', 'code')->ignore($account->id)],
            'name' => ['required', 'string', 'max:150'],
            'group' => ['required', Rule::in(Account::GROUPS)],
            'parent_id' => ['nullable', Rule::exists('accounts', 'id')],
        ]);

        $this->service->update($request->user(), $account, $validated);

        return redirect()->route('accounts.show', $account)->with('status', __('ui.accounting.account_updated'));
    }

    public function toggleActive(Request $request, Account $account): RedirectResponse
    {
        $this->service->setActive($request->user(), $account, ! $account->is_active);

        return back()->with('status', $account->is_active ? __('ui.accounting.account_activated') : __('ui.accounting.account_deactivated'));
    }

    /** Akun yang boleh menjadi induk: tingkat atas saja (maks dua tingkat). */
    private function parentOptions(?Account $except = null)
    {
        return Account::whereNull('parent_id')
            ->when($except !== null, fn ($q) => $q->where('id', '!=', $except->id))
            ->orderBy('code')->get();
    }
}
