<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Services\LedgerService;
use App\Modules\Master\Models\Branch;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Buku besar (F1.2): satu akun, satu rentang, saldo berjalan. */
class LedgerController extends Controller
{
    public function __construct(private readonly LedgerService $ledger) {}

    public function index(Request $request): View
    {
        $accounts = Account::orderBy('code')->get();
        $branches = Branch::orderBy('name')->get();

        $accountId = (int) $request->query('akun', 0);
        $account = $accounts->firstWhere('id', $accountId) ?? $accounts->first();

        $from = (string) $request->query('dari', now()->startOfMonth()->toDateString());
        $to = (string) $request->query('sampai', now()->toDateString());
        if (! $this->isDate($from)) {
            $from = now()->startOfMonth()->toDateString();
        }
        if (! $this->isDate($to) || $to < $from) {
            $to = $from;
        }
        $branchParam = $request->query('cabang');
        $selectedBranch = is_numeric($branchParam) ? (int) $branchParam : null;

        $result = $account !== null
            ? $this->ledger->ledger($account, $from, $to, $selectedBranch)
            : ['opening' => Money::zero(), 'closing' => Money::zero(), 'total_debit' => Money::zero(), 'total_credit' => Money::zero(), 'entries' => []];

        return view('accounting.ledger.index', compact('accounts', 'branches', 'account', 'from', 'to', 'selectedBranch', 'result'));
    }

    private function isDate(string $value): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1
            && strtotime($value) !== false;
    }
}
