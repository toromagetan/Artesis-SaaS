<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Journal;
use App\Modules\Accounting\Services\PostingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Jurnal baca-saja (M.9): daftar + detail halaman penuh; koreksi
 * hanya jurnal balik ber-alasan (journals.reverse).
 */
class JournalController extends Controller
{
    public function index(Request $request): View
    {
        $source = (string) $request->query('sumber', '');
        $period = (string) $request->query('periode', '');

        $journals = Journal::with('creator')
            ->withSum('lines as total_debit', 'debit')
            ->when($source !== '', fn ($q) => $q->where('source_type', $source))
            ->when(preg_match('/^\d{4}-\d{2}$/', $period) === 1, fn ($q) => $q->whereRaw('substr(journal_date, 1, 7) = ?', [$period]))
            ->orderByDesc('journal_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $sources = Journal::select('source_type')->distinct()->orderBy('source_type')->pluck('source_type');

        return view('accounting.journals.index', compact('journals', 'source', 'period', 'sources'));
    }

    public function show(Journal $journal): View
    {
        $journal->load(['lines.account', 'lines.branch', 'creator', 'reversalOf']);
        $reversal = Journal::where('reversal_of_id', $journal->id)->first();

        return view('accounting.journals.show', compact('journal', 'reversal'));
    }

    public function reverse(Request $request, Journal $journal, PostingService $posting): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $reversal = $posting->reverse($journal->load('lines.account'), $validated['reason'], $request->user());

        return redirect()->route('journals.show', $reversal)->with('status', __('ui.saved'));
    }
}
