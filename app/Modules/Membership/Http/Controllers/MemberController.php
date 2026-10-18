<?php

namespace App\Modules\Membership\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Comms\Models\WaMessage;
use App\Modules\CRM\Models\Customer;
use App\Modules\Lab\Models\LensProcess;
use App\Modules\Membership\Models\MembershipLevel;
use App\Modules\Membership\Models\PointsLedger;
use App\Modules\Membership\Services\MemberPortalService;
use App\Modules\Membership\Services\PointsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Membership (F3.6, PRD 8): daftar member = pelanggan + level + saldo
 * poin; penyesuaian poin manual ber-alasan (points.adjust); daftar
 * follow-up purnajual (proses lensa Complete ≥ 7 hari).
 */
class MemberController extends Controller
{
    public function __construct(private readonly PointsService $points) {}

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $levelId = $request->query('level');

        $customers = Customer::with('level')
            ->where('is_active', true)
            ->when($q !== '', fn ($query) => $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")->orWhere('phone', 'like', "%{$q}%");
            }))
            ->when($levelId, fn ($query) => $query->where('membership_level_id', (int) $levelId))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $balances = PointsLedger::whereIn('customer_id', $customers->pluck('id'))
            ->groupBy('customer_id')
            ->selectRaw('customer_id, SUM(points) as total')
            ->pluck('total', 'customer_id');

        $levels = MembershipLevel::orderBy('min_accumulation')->get();

        return view('members.index', compact('customers', 'balances', 'levels', 'q', 'levelId'));
    }

    public function adjustForm(Customer $customer): View
    {
        $balance = $this->points->balance($customer);
        $history = PointsLedger::where('customer_id', $customer->id)
            ->orderByDesc('id')->limit(20)->get();

        return view('members.adjust', compact('customer', 'balance', 'history'));
    }

    public function storeAdjust(Request $request, Customer $customer): RedirectResponse
    {
        $validated = $request->validate([
            'points' => ['required', 'integer', 'not_in:0'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $this->points->adjust($request->user(), $customer, (int) $validated['points'], (string) $validated['reason']);

        return redirect()->route('members.adjust', $customer)->with('status', __('ui.members.adjusted'));
    }

    /** Terbitkan tautan portal (token lama tercabut) — F3.7. */
    public function issuePortalToken(Customer $customer, MemberPortalService $portal): RedirectResponse
    {
        $token = $portal->issueToken($customer);

        return redirect()->route('members.index')
            ->with('portal_url', route('portal.show', $token));
    }

    /** Cabut tautan portal pelanggan — F3.7. */
    public function revokePortalToken(Customer $customer, MemberPortalService $portal): RedirectResponse
    {
        $portal->revoke($customer);

        return redirect()->route('members.index')->with('status', __('ui.members.portal_revoked'));
    }

    /** Daftar follow-up purnajual: kacamata selesai ≥ 7 hari yang lalu. */
    public function followUp(): View
    {
        $processes = LensProcess::with(['sale.customer', 'sale.branch'])
            ->where('status', 'done')
            ->whereDate('completed_at', '<=', today()->subDays(7))
            ->orderBy('completed_at')
            ->paginate(25);

        // Tandai proses yang sapaan purnajualnya sudah terkirim/di antre
        // (pesan after_service menyebut nomor transaksi di badannya).
        $customerIds = $processes->map(fn ($p) => $p->sale?->customer_id)->filter()->unique()->all();
        $sentMessages = WaMessage::where('template_key', 'after_service')
            ->whereIn('customer_id', $customerIds)->get(['customer_id', 'body']);
        foreach ($processes as $process) {
            $sale = $process->sale;
            $process->wa_sent = $sale !== null && $sentMessages->contains(
                fn ($m) => (int) $m->customer_id === (int) $sale->customer_id && str_contains((string) $m->body, (string) $sale->number),
            );
        }

        return view('members.follow-up', compact('processes'));
    }
}
