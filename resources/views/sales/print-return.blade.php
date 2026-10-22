<x-print-layout :title="__('ui.sales.return_doc')" :store-name="$storeName" :printed-by="$printedBy" paper="A5 portrait">
    <h1 style="font-size:14pt; margin:0 0 2mm;">{{ __('ui.sales.return_doc') }} — {{ $return->number }}</h1>
    <p style="margin:0 0 4mm;">
        {{ __('ui.sales.number') }}: {{ $return->sale->number }} ·
        {{ __('ui.sales.customer') }}: {{ $return->sale->customer->name }} ·
        {{ __('ui.sales.date') }}: {{ $return->created_at->format('d/m/Y') }} ·
        {{ $return->sale->branch->name }}
    </p>
    <p style="margin:0 0 4mm;">
        {{ __('ui.sales.return_reason') }}: {{ $return->reason }} ·
        {{ __('ui.sales.refund_method') }}: {{ __('ui.sales.refund_methods.'.$return->refund_method) }}
    </p>

    <table style="width:100%; border-collapse:collapse;" border="1" cellpadding="4">
        <thead>
            <tr>
                <th>{{ __('ui.sales.item') }}</th>
                <th>{{ __('ui.sales.qty') }}</th>
                <th>{{ __('ui.sales.condition') }}</th>
                <th>{{ __('ui.sales.amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($return->items as $item)
                <tr>
                    <td>{{ $item->saleItem->product_name_snapshot }}</td>
                    <td>{{ $item->qty }}</td>
                    <td>{{ $item->condition }}</td>
                    <td>{{ \App\Support\Money::fromDecimal((string) $item->amount)->format() }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr><td colspan="3" style="text-align:right;"><strong>{{ __('ui.sales.total') }}</strong></td><td><strong>{{ \App\Support\Money::fromDecimal((string) $return->total_amount)->format() }}</strong></td></tr>
        </tfoot>
    </table>

    @if ($return->refund_method === 'store_credit')
        <p style="margin-top:4mm;">{{ __('ui.sales.return_credit_note') }}</p>
    @endif
</x-print-layout>
