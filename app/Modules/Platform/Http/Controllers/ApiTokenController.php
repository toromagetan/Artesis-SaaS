<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Models\ApiToken;
use App\Modules\Platform\Services\ApiTokenService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Pengaturan Token API (F4.6), sepagar api_tokens.manage.
 * Token mentah tampil SATU KALI lewat flash sesudah penerbitan.
 */
class ApiTokenController extends Controller
{
    public function __construct(private readonly ApiTokenService $tokens) {}

    public function index(): View
    {
        $tokens = ApiToken::orderByDesc('id')->get();

        return view('settings.api-tokens', compact('tokens'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'scopes' => ['required', 'array', 'min:1'],
            'scopes.*' => ['string', 'in:'.implode(',', ApiToken::SCOPES)],
        ]);
        $issued = $this->tokens->issue($request->user(), $validated['name'], $validated['scopes']);

        return redirect()->route('api-tokens.index')
            ->with('status', __('ui.api_tokens.issued'))
            ->with('plain_token', $issued['plain']);
    }

    public function revoke(Request $request, ApiToken $token): RedirectResponse
    {
        $this->tokens->revoke($request->user(), $token);

        return redirect()->route('api-tokens.index')->with('status', __('ui.api_tokens.revoked'));
    }
}
