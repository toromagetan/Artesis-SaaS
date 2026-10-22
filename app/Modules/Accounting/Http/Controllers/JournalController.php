<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Journal;
use App\Modules\Accounting\Services\ManualJournalService;
use App\Modules\Accounting\Services\PostingService;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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

    /** Voucher jurnal tercetak (audit 23 Okt 2026 B4). */
    public function print(Journal $journal): View
    {
        $journal->load(['lines.account', 'lines.branch', 'creator']);

        return view('accounting.journals.print', [
            'journal' => $journal,
            'storeName' => Setting::value('store.name', 'Lentera'),
            'printedBy' => auth()->user(),
        ]);
    }

    public function reverse(Request $request, Journal $journal, PostingService $posting): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $reversal = $posting->reverse($journal->load('lines.account'), $validated['reason'], $request->user());

        return redirect()->route('journals.show', $reversal)->with('status', __('ui.saved'));
    }

    /** Formulir jurnal manual (F1.2, izin journals.manual). */
    public function create(): View
    {
        return view('accounting.journals.create', [
            'accounts' => Account::where('is_active', true)->orderBy('code')->get(),
            'branches' => Branch::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, ManualJournalService $manual): RedirectResponse
    {
        $validated = $request->validate([
            'journal_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array'],
            'lines.*.account_id' => ['nullable', Rule::exists('accounts', 'id')],
            'lines.*.branch_id' => ['nullable', Rule::exists('branches', 'id')],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.memo' => ['nullable', 'string', 'max:255'],
        ]);

        $lines = collect($validated['lines'])
            ->filter(fn ($line) => ! empty($line['account_id']))
            ->values()
            ->all();

        if (count($lines) < 2) {
            throw ValidationException::withMessages(['lines' => __('ui.accounting.lines_min')]);
        }

        $journal = $manual->postManual(
            $request->user(),
            $validated['journal_date'],
            $validated['description'] ?? null,
            $lines,
        );

        return redirect()->route('journals.show', $journal)->with('status', __('ui.accounting.manual_created'));
    }
}
