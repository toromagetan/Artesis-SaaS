<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Services\AccountService;
use App\Modules\Identity\Services\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Illuminate\View\View;

/**
 * Bagan akun (F1.1): baca untuk accounts.view; kelola (buat/ubah/
 * nonaktif — tanpa hapus) untuk accounts.manage.
 *
 * Sejak ISO-2 bagan adalah milik per ember tenant: daftar dan
 * halaman akun hanya menampilkan ember aktor; akun ember lain
 * menjawab 404 seolah tidak ada. Baris jurnal pada halaman akun
 * tetap terpagar ember aktor lewat jurnal induknya (F-3).
 */
class AccountController extends Controller
{
    public function __construct(private readonly AccountService $service) {}

    public function index(Request $request): View
    {
        $accounts = Account::inBucket(Account::bucketOf($request->user()))
            ->with('parent')->orderBy('code')->get();

        return view('accounting.accounts.index', compact('accounts'));
    }

    public function show(Request $request, Account $account): View
    {
        $this->assertVisible($request, $account);
        $account->load(['parent', 'children']);
        $context = TenantContext::current();
        $lines = fn () => $account->lines()
            ->whereHas('journal', fn ($q) => $context->constrainByTenant($q));
        $totals = $lines()
            ->selectRaw('COALESCE(SUM(debit),0) as total_debit, COALESCE(SUM(credit),0) as total_credit, COUNT(*) as line_count')
            ->first();
        $recentLines = $lines()->with('journal')->orderByDesc('id')->limit(10)->get();

        return view('accounting.accounts.show', compact('account', 'totals', 'recentLines'));
    }

    public function create(Request $request): View
    {
        return view('accounting.accounts.create', ['parents' => $this->parentOptions($request)]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20', $this->uniqueCodeRule($request)],
            'name' => ['required', 'string', 'max:150'],
            'group' => ['required', Rule::in(Account::GROUPS)],
            'parent_id' => ['nullable', Rule::exists('accounts', 'id')],
        ]);

        $account = $this->service->create($request->user(), $validated);

        return redirect()->route('accounts.show', $account)->with('status', __('ui.accounting.account_created'));
    }

    public function edit(Request $request, Account $account): View
    {
        $this->assertVisible($request, $account);

        return view('accounting.accounts.edit', [
            'account' => $account,
            'parents' => $this->parentOptions($request, $account),
            'hasLines' => $account->lines()->exists(),
        ]);
    }

    public function update(Request $request, Account $account): RedirectResponse
    {
        $this->assertVisible($request, $account);
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20', $this->uniqueCodeRule($request, $account)],
            'name' => ['required', 'string', 'max:150'],
            'group' => ['required', Rule::in(Account::GROUPS)],
            'parent_id' => ['nullable', Rule::exists('accounts', 'id')],
        ]);

        $this->service->update($request->user(), $account, $validated);

        return redirect()->route('accounts.show', $account)->with('status', __('ui.accounting.account_updated'));
    }

    public function toggleActive(Request $request, Account $account): RedirectResponse
    {
        $this->assertVisible($request, $account);
        $this->service->setActive($request->user(), $account, ! $account->is_active);

        return back()->with('status', $account->is_active ? __('ui.accounting.account_activated') : __('ui.accounting.account_deactivated'));
    }

    /** Akun ember lain tidak terlihat aktor ini — 404, bukan 403 (ISO-2). */
    private function assertVisible(Request $request, Account $account): void
    {
        if ($account->tenant_id !== Account::bucketOf($request->user())) {
            abort(404);
        }
    }

    /** Kode unik hanya di dalam ember aktor (ISO-2). */
    private function uniqueCodeRule(Request $request, ?Account $ignore = null): Unique
    {
        $bucket = Account::bucketOf($request->user());
        $rule = Rule::unique('accounts', 'code')
            ->where(fn ($q) => $bucket === null ? $q->whereNull('tenant_id') : $q->where('tenant_id', $bucket));

        return $ignore !== null ? $rule->ignore($ignore->id) : $rule;
    }

    /** Akun yang boleh menjadi induk: tingkat atas saja (maks dua tingkat), seember aktor. */
    private function parentOptions(Request $request, ?Account $except = null)
    {
        return Account::inBucket(Account::bucketOf($request->user()))
            ->whereNull('parent_id')
            ->when($except !== null, fn ($q) => $q->where('id', '!=', $except->id))
            ->orderBy('code')->get();
    }
}
