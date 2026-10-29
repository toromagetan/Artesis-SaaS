<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Models\AiSetting;
use App\Modules\Platform\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\View\View;

/**
 * Pengaturan Asisten AI (F5.1) — endpoint kompatibel OpenAI +
 * nama model + API key terenkripsi, diatur owner lewat UI
 * (keputusan owner 2026-10-18). API key kosong saat simpan =
 * nilai lama dipertahankan; key tidak pernah tampil penuh.
 */
#[Middleware('plan.feature:ai_assistant')]
class AiSettingController extends Controller
{
    public function edit(): View
    {
        return view('settings.ai', ['setting' => AiSetting::current()]);
    }

    public function update(Request $request, AuditLogger $audit): RedirectResponse
    {
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
