<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Models\User;
use App\Modules\Platform\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

/**
 * Pemulihan istimewa (paritas Privileged Recovery Artesis):
 * jalur publik khusus akun OWNER aktif bila akses biasa hilang.
 * Respons selalu generik (anti-enumerasi); hanya Owner aktif
 * yang benar-benar dibuatkan token broker standar + notifikasi
 * ResetPassword. Setiap percobaan tercatat di Log Aktivitas.
 * Reset-nya sendiri memakai alur reset sandi standar.
 */
class PrivilegedRecoveryController extends Controller
{
    public function create(): View
    {
        return view('auth.recovery');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);
        $email = mb_strtolower(trim((string) $validated['email']));

        $user = User::where('email', $email)
            ->where('is_active', true)
            ->get()
            ->first(fn (User $candidate) => $candidate->hasRole('Owner'));

        if ($user !== null) {
            $token = Password::broker()->createToken($user);
            $user->sendPasswordResetNotification($token);
        }

        app(AuditLogger::class)->log(
            action: 'recovery.requested',
            subjectType: 'User',
            subjectId: $user?->id,
            newValues: [
                'matched' => $user !== null,
                'email_hash' => substr(hash('sha256', $email), 0, 12),
            ],
        );

        return back()->with('status', __('ui.recovery.generic_status'));
    }
}
