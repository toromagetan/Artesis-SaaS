<?php

namespace App\Modules\Comms\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Comms\Models\WaTemplate;
use App\Modules\Platform\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Kelola template WhatsApp (F3.4). Kunci template tetap (Blueprint
 * B.7); yang disunting hanya badannya. Terpagar wa.templates.
 */
class WaTemplateController extends Controller
{
    public function index(): View
    {
        $templates = WaTemplate::orderBy('key')->get();

        return view('comms.wa-templates', compact('templates'));
    }

    public function edit(WaTemplate $template): View
    {
        return view('comms.wa-template-edit', compact('template'));
    }

    public function update(Request $request, WaTemplate $template, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $template->update(['body' => $validated['body']]);

        $audit->log(
            action: 'wa.template.update',
            subjectType: 'wa_template',
            subjectId: $template->id,
            newValues: ['key' => $template->key],
            actor: $request->user(),
        );

        return redirect()->route('wa-templates.index')->with('status', __('ui.wa.template_saved'));
    }

    /**
     * Persetujuan template (F3.8): template kampanye WAJIB approved_by
     * terisi sebelum kampanye apa pun boleh dikirim (CampaignService).
     * Terpagar izin wa.campaigns.approve (hanya Owner).
     */
    public function approve(Request $request, WaTemplate $template, AuditLogger $audit): RedirectResponse
    {
        $template->update(['approved_by' => $request->user()->id]);

        $audit->log(
            action: 'wa.template.approve',
            subjectType: 'wa_template',
            subjectId: $template->id,
            newValues: ['key' => $template->key],
            actor: $request->user(),
        );

        return redirect()->route('wa-templates.index')->with('status', __('ui.wa.template_approved'));
    }
}
