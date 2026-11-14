<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Services\TenantContext;
use App\Modules\Platform\Models\AiSetting;
use App\Modules\Platform\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\View\View;

/**
 * Pengaturan Asisten AI (F5.1) — endpoint kompatibel OpenAI +
 * nama model + API key terenkripsi. API key kosong saat simpan =
 * nilai lama dipertahankan; key tidak pernah tampil penuh.
 *
 * Sejak AI bayar per penggunaan (keputusan owner 2026-11-14) kunci
 * adalah milik PLATFORM: konfigurasi hanya dapat diubah aktor
 * platform atau pemilik instalasi mandiri. Aktor tenant mengelola
 * saldo pertanyaannya di halaman Paket Saya.
 */
#[Middleware('plan.feature:ai_assistant')]
class AiSettingController extends Controller
{
    public function edit(Request $request): View|RedirectResponse
    {
        if ($this->isTenantActor($request)) {
            return redirect()->route('plans.my');
        }

        return view('settings.ai', ['setting' => AiSetting::current()]);
    }

    /** Aktor dengan konteks tenant sah tidak menyentuh konfigurasi platform. */
    private function isTenantActor(Request $request): bool
    {
        return TenantContext::for($request->user())->isValid();
    }

    public function update(Request $request, AuditLogger $audit): RedirectResponse
    {
        if ($this->isTenantActor($request)) {
            abort(403);
        }

        $validated = $request->validate([
            'base_url' => ['required', 'url', 'max:255'],
            'model' => ['required', 'string', 'max:100'],
            'api_key' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $setting = AiSetting::current();
        $credentials = $setting->credentials_encrypted ?? [];
        if (! empty($validated['api_key'])) {
            $credentials['api_key'] = $validated['api_key'];
        }
        $setting->update([
            'base_url' => rtrim($validated['base_url'], '/'),
            'model' => $validated['model'],
            'credentials_encrypted' => $credentials,
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ]);
        $audit->log(action: 'ai_setting.update', subjectType: 'ai_setting', subjectId: $setting->id,
            newValues: ['base_url' => $setting->base_url, 'model' => $setting->model, 'is_active' => $setting->is_active],
            actor: $request->user());

        return redirect()->route('ai-setting.edit')->with('status', __('ui.ai.saved'));
    }
}
