<?php

namespace App\Modules\Membership\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Master\Models\Setting;
use App\Modules\Membership\Models\MembershipLevel;
use App\Modules\Platform\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Kelola level membership + pengaturan poin (F3.6). Terpagar
 * members.manage. Pengaturan poin disimpan sebagai settings:
 * points.earn_amount / points.redeem_value / points.expiry_months.
 */
class MembershipLevelController extends Controller
{
    public function index(): View
    {
        $levels = MembershipLevel::orderBy('min_accumulation')->orderBy('id')->get();
        $settings = [
            'earn_amount' => (string) Setting::value('points.earn_amount', '10000'),
            'redeem_value' => (string) Setting::value('points.redeem_value', '100'),
            'expiry_months' => (string) Setting::value('points.expiry_months', '12'),
        ];

        return view('members.levels', compact('levels', 'settings'));
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $validated = $this->validateLevel($request);
        $level = MembershipLevel::create($validated + ['is_active' => true]);
        $audit->log(action: 'members.level_create', subjectType: 'membership_level', subjectId: $level->id, newValues: ['name' => $level->name], actor: $request->user());

        return redirect()->route('members.levels')->with('status', __('ui.members.level_saved'));
    }

    public function update(Request $request, MembershipLevel $level, AuditLogger $audit): RedirectResponse
    {
        $level->update($this->validateLevel($request));
        $audit->log(action: 'members.level_update', subjectType: 'membership_level', subjectId: $level->id, newValues: ['name' => $level->name], actor: $request->user());

        return redirect()->route('members.levels')->with('status', __('ui.members.level_saved'));
    }

    public function toggleActive(MembershipLevel $level, AuditLogger $audit, Request $request): RedirectResponse
    {
        $level->update(['is_active' => ! $level->is_active]);
        $audit->log(action: 'members.level_toggle', subjectType: 'membership_level', subjectId: $level->id, newValues: ['is_active' => $level->is_active], actor: $request->user());

        return redirect()->route('members.levels')->with('status', __('ui.members.level_saved'));
    }

    public function updateSettings(Request $request, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'earn_amount' => ['required', 'integer', 'min:1'],
            'redeem_value' => ['required', 'numeric', 'min:0'],
            'expiry_months' => ['required', 'integer', 'min:0', 'max:120'],
        ]);
        Setting::set('points.earn_amount', (string) $validated['earn_amount']);
        Setting::set('points.redeem_value', (string) $validated['redeem_value']);
        Setting::set('points.expiry_months', (string) $validated['expiry_months']);
        $audit->log(action: 'members.points_settings', subjectType: 'setting', subjectId: null, newValues: $validated, actor: $request->user());

        return redirect()->route('members.levels')->with('status', __('ui.members.settings_saved'));
    }

    /** @return array<string, mixed> */
    private function validateLevel(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'min_accumulation' => ['required', 'numeric', 'min:0'],
            'point_multiplier' => ['required', 'numeric', 'min:0', 'max:99.99'],
            'benefits' => ['nullable', 'string'],
        ]);
    }
}
