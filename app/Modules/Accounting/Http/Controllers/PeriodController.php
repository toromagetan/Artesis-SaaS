<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Services\PostingService;
use App\Modules\Identity\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Tutup buku (F1.5): kunci/buka kunci periode. Kunci dipagari
 * izin periods.lock; buka kunci periods.unlock DAN peran Owner
 * (Blueprint E.3: unlock hanya Owner, wajib alasan, teraudit).
 */
class PeriodController extends Controller
{
    public function __construct(private readonly PostingService $posting) {}

    public function index(): View
    {
        $locks = DB::table('period_locks')->get()->keyBy('period');
        $users = User::whereIn('id', $locks->flatMap(fn ($l) => [$l->locked_by, $l->unlocked_by])->filter()->unique())->get()->keyBy('id');

        $periods = collect();
        for ($i = 0; $i < 12; $i++) {
            $periods->push(now()->subMonths($i)->format('Y-m'));
        }
        foreach ($locks->keys() as $period) {
            if (! $periods->contains($period)) {
                $periods->push($period);
            }
        }

        $rows = $periods->sortDesc()->values()->map(function ($period) use ($locks, $users) {
            $lock = $locks->get($period);
            $isLocked = $lock !== null && $lock->unlocked_by === null;

            return [
                'period' => $period,
                'locked' => $isLocked,
                'ever_locked' => $lock !== null,
                'locked_by' => $lock ? ($users->get($lock->locked_by)?->name ?? null) : null,
                'locked_at' => $lock?->locked_at,
                'unlocked_by' => $lock && $lock->unlocked_by ? ($users->get($lock->unlocked_by)?->name ?? null) : null,
                'unlock_reason' => $lock?->unlock_reason,
            ];
        });

        return view('accounting.periods.index', compact('rows'));
    }

    public function lock(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'period' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
        ]);
        $this->posting->lockPeriod($request->user(), $validated['period']);

        return redirect()->route('periods.index')->with('status', __('ui.periods.locked_msg', ['period' => $validated['period']]));
    }

    public function unlock(Request $request): RedirectResponse
    {
        // Buka kunci = hak Owner (Blueprint E.3), di luar izin.
        if (! $request->user()->hasRole('Owner')) {
            abort(403);
        }
        $validated = $request->validate([
            'period' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'reason' => ['required', 'string', 'max:255'],
        ]);
        $this->posting->unlockPeriod($request->user(), $validated['period'], $validated['reason']);

        return redirect()->route('periods.index')->with('status', __('ui.periods.unlocked_msg', ['period' => $validated['period']]));
    }
}
