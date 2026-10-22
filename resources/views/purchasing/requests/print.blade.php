<x-print-layout :title="__('ui.print_doc.pr_doc')" :store-name="$storeName" :printed-by="$printedBy" paper="A5 portrait">
    <h1 style="font-size:14pt; margin:0 0 2mm;">{{ __('ui.print_doc.pr_doc') }} — {{ $pr->number }}</h1>
    <p style="margin:0 0 4mm;">
        {{ __('ui.purchasing.branch') }}: {{ $pr->branch->name }} ·
        {{ __('ui.purchasing.requester') }}: {{ $pr->requester?->name }} ·
        {{ __('ui.purchasing.date') }}: {{ $pr->created_at->format('d/m/Y') }} ·
        {{ __('ui.purchasing.status') }}: {{ $pr->status }}
        @if ($pr->sale) · {{ __('ui.sales.number') }}: {{ $pr->sale->number }} @endif
    </p>
    @if ($pr->notes)
        <p style="margin:0 0 4mm;">{{ __('ui.purchasing.notes') }}: {{ $pr->notes }}</p>
    @endif

    <table style="width:100%; border-collapse:collapse;" border="1" cellpadding="4">
        <thead>
            <tr>
                <th>{{ __('ui.purchasing.product') }}</th>
                <th>{{ __('ui.purchasing.qty') }}</th>
                <th>{{ __('ui.purchasing.estimated_price') }}</th>
                <th>{{ __('ui.purchasing.supplier') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($pr->items as $item)
                <tr>
                    <td>{{ $item->product->name }}</td>
                    <td>{{ $item->qty }}</td>
                    <td>{{ $item->estimated_price !== null ? \App\Support\Money::fromDecimal((string) $item->estimated_price)->format() : '—' }}</td>
                    <td>{{ $item->supplier?->name ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</x-print-layout>
