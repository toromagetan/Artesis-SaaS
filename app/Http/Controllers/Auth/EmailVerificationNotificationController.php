<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Pengiriman ulang email verifikasi (B-01). Hanya pernah
 * bertindak atas pengguna yang sedang masuk — tidak menerima
 * alamat dari input — sehingga tidak membocorkan keberadaan
 * alamat mana pun. Rute dibatasi throttle di routes/auth.php.
 */
class EmailVerificationNotificationController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'verification-link-sent');
    }
}
