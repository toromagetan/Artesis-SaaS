<?php

namespace App\Modules\Comms\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Comms\Models\WaCampaign;
use App\Modules\Comms\Models\WaTemplate;
use App\Modules\Comms\Services\CampaignService;
use App\Modules\Master\Models\Branch;
use App\Modules\Membership\Models\MembershipLevel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Kampanye WA (F3.8): buat (wa.campaigns) -> setujui
 * (wa.campaigns.approve, hanya Owner) -> kirim. Gerbang persetujuan
 * ganda ditegakkan CampaignService; controller hanya memetakan hasil.
 */
class WaCampaignController extends Controller
{
    public function __construct(private readonly CampaignService $campaigns) {}

    public function index(): View
    {
        $campaigns = WaCampaign::with(['template', 'approver', 'creator'])
            ->orderByDesc('id')->paginate(25);
        $templates = WaTemplate::orderBy('key')->get();
        $levels = MembershipLevel::orderBy('min_accumulation')->get();
        $branches = Branch::orderBy('name')->get();

        return view('comms/wa-campaigns', compact('campaigns', 'templates', 'levels', 'branches'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'template_id' => ['required', 'exists:wa_templates,id'],
            'segment_type' => ['required', 'in:all,level,branch'],
            'level_id' => ['nullable', 'integer'],
            'branch_id' => ['nullable', 'integer'],
        ]);

        $segment = ['type' => $validated['segment_type']];
        if ($validated['segment_type'] === 'level') {
            $segment['level_id'] = (int) ($validated['level_id'] ?? 0);
        }
        if ($validated['segment_type'] === 'branch') {
            $segment['branch_id'] = (int) ($validated['branch_id'] ?? 0);
        }

        $this->campaigns->create($request->user(), (string) $validated['name'], (int) $validated['template_id'], $segment);

        return redirect()->route('wa-campaigns.index')->with('status', __('ui.campaigns.created'));
    }

    public function show(WaCampaign $campaign): View
    {
        $campaign->load(['template', 'approver', 'creator']);
        $messages = $campaign->messages()->with('customer')->orderByDesc('id')->paginate(25);
        $previewCount = $this->campaigns->resolveRecipients($campaign)->count();

        return view('comms/wa-campaign-show', compact('campaign', 'messages', 'previewCount'));
    }

    public function approve(Request $request, WaCampaign $campaign): RedirectResponse
    {
        $this->campaigns->approve($request->user(), $campaign);

        return redirect()->route('wa-campaigns.show', $campaign)->with('status', __('ui.campaigns.approved'));
    }

    public function send(Request $request, WaCampaign $campaign): RedirectResponse
    {
        $this->campaigns->requestSend($request->user(), $campaign);

        return redirect()->route('wa-campaigns.show', $campaign)->with('status', __('ui.campaigns.sending'));
    }

    public function cancel(Request $request, WaCampaign $campaign): RedirectResponse
    {
        $this->campaigns->cancel($request->user(), $campaign);

        return redirect()->route('wa-campaigns.index')->with('status', __('ui.campaigns.cancelled'));
    }
}
