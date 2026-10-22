<x-print-layout :title="__('ui.print_doc.journal_doc')" :store-name="$storeName" :printed-by="$printedBy" paper="A5 portrait">
    <h1 style="font-size:14pt; margin:0 0 2mm;">{{ __('ui.print_doc.journal_doc') }} — {{ $journal->number }}</h1>
    <p style="margin:0 0 4mm;">
        {{ __('ui.accounting.date') }}: {{ $journal->journal_date->format('d/m/Y') }} ·
        {{ __('ui.accounting.source') }}: {{ $journal->source_type }}@if ($journal->source_id) #{{ $journal->source_id }}@endif ·
        {{ __('ui.accounting.status') }}: {{ $journal->status }}
    </p>
    <p style="margin:0 0 4mm;">{{ __('ui.accounting.description') }}: {{ $journal->description }}</p>

    <table style="width:100%; border-collapse:collapse;" border="1" cellpadding="4">
        <thead>
            <tr>
                <th>{{ __('ui.accounting.account') }}</th>
                <th>{{ __('ui.accounting.branch') }}</th>
                <th>{{ __('ui.accounting.debit') }}</th>
                <th>{{ __('ui.accounting.credit') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($journal->lines as $line)
                <tr>
                    <td>{{ $line->account->code }} — {{ $line->account->name }}</td>
                    <td>{{ $line->branch?->name ?? '—' }}</td>
                    <td>{{ \App\Support\Money::fromDecimal((string) $line->debit)->format() }}</td>
                    <td>{{ \App\Support\Money::fromDecimal((string) $line->credit)->format() }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="2" style="text-align:right;"><strong>{{ __('ui.accounting.total') }}</strong></td>
                <td><strong>{{ \App\Support\Money::fromDecimal((string) $journal->lines->sum('debit'))->format() }}</strong></td>
                <td><strong>{{ \App\Support\Money::fromDecimal((string) $journal->lines->sum('credit'))->format() }}</strong></td>
            </tr>
        </tfoot>
    </table>

    <p style="margin-top:6mm;">{{ __('ui.print_doc.made_by') }}: {{ $journal->creator?->name }}</p>
</x-print-layout>
