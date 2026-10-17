<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Pengalih bahasa (ADR-011). Tersedia untuk tamu (sesi) dan pengguna
 * login (tersimpan sebagai preferensi akun).
 */
class LocaleController extends Controller
{
    public function switch(Request $request, string $locale): RedirectResponse
    {
        abort_unless(in_array($locale, config('app.supported_locales', ['id', 'en']), true), 404);

        $request->session()->put('locale', $locale);

        if ($user = $request->user()) {
            $user->forceFill(['locale' => $locale])->save();
        }

        return back();
    }
}
