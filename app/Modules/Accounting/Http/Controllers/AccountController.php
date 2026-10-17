<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Account;
use Illuminate\View\View;

/** Bagan akun baca-saja di MVP (kelola penuh di Fase 1). */
class AccountController extends Controller
{
    public function index(): View
    {
        $accounts = Account::orderBy('code')->get();

        return view('accounting.accounts.index', compact('accounts'));
    }
}
