<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Services\TenantSignupService;
use App\Modules\Platform\Services\TurnstileVerifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Pendaftaran mandiri tenant (M5): halaman publik /daftar untuk
 * bisnis optik baru. Berbeda dari registrasi pengguna (yang tidak
 * ada) — ini membuat TENANT baru beserta pemiliknya, paket Free.
 */
class SignupController extends Controller
{
    public function __construct(
        private readonly TenantSignupService $signup,
        private readonly TurnstileVerifier $turnstile,
    ) {}

    public function create(): View
    {
        return view('platform.signup', [
            // Widget dirender begitu site key ada (termasuk keadaan
            // salah konfigurasi, agar kegagalan terlihat kasat mata).
            'turnstileEnabled' => $this->turnstile->siteKey() !== null,
            'turnstileSiteKey' => $this->turnstile->siteKey(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:100'],
            'owner_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'branch_name' => ['required', 'string', 'max:100'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        // Anti-bot (S-05): begitu Turnstile terkonfigurasi (atau
        // salah terkonfigurasi — satu kunci terisi), token wajib
        // terverifikasi SEBELUM tenant apa pun dibuat; kegagalan =
        // kesalahan validasi biasa, tanpa data tercipta. Input
        // diteruskan apa adanya (mixed) agar bentuk token yang
        // tidak sah ditolak verifier, bukan menjadi error 500.
        if ($this->turnstile->requiresVerification()
            && ! $this->turnstile->verify($request->input('cf-turnstile-response'), $request->ip())) {
            throw ValidationException::withMessages([
                'cf-turnstile-response' => __('ui.signup.bot_check_failed'),
            ]);
        }

        $user = $this->signup->register($data);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')
            ->with('status', __('ui.signup.success'));
    }
}
