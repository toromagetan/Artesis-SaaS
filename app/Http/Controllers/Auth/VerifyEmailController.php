<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

/**
 * Pemenuhan tautan verifikasi bertanda tangan (B-01). Permintaan
 * sudah lolos middleware `signed` + throttle di rute; request
 * framework memvalidasi id & hash email pemiliknya.
 */
class VerifyEmailController extends Controller
{
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        $request->fulfill();

        return redirect()->route('dashboard')
            ->with('status', __('ui.auth.verification.verified'));
    }
}
