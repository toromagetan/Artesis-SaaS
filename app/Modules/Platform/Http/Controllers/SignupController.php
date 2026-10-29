<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Services\TenantSignupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Pendaftaran mandiri tenant (M5): halaman publik /daftar untuk
 * bisnis optik baru. Berbeda dari registrasi pengguna (yang tidak
 * ada) — ini membuat TENANT baru beserta pemiliknya, paket Free.
 */
class SignupController extends Controller
{
    public function __construct(private readonly TenantSignupService $signup) {}

    public function create(): View
    {
        return view('platform.signup');
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

        $user = $this->signup->register($data);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')
            ->with('status', __('ui.signup.success'));
    }
}
