<x-print-layout :title="__('ui.sales.invoice_doc')" :store-name="$storeName" :paper="'A5 portrait'">
    <h1 style="font-size:14pt; margin:0 0 2mm;">{{ __('ui.sales.invoice_doc') }} — {{ $sale->number }}</h1>
    <p style="margin:0 0 4mm;">{{ $sale->customer->name }} · {{ $sale->customer->phone }} · {{ $sale->sale_date->format('d/m/Y') }} · {{ $sale->branch->name }}</p>

    <table style="width:100%; border-collapse:collapse;" border="1" cellpadding="4">
        <thead>
            <tr>
                <th>{{ __('ui.sales.item') }}</th>
                <th>{{ __('ui.sales.qty') }}</th>
                <th>{{ __('ui.sales.unit_price') }}</th>
                <th>{{ __('ui.sales.discount') }}</th>
                <th>{{ __('ui.sales.line_total') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($sale->items as $item)
                <tr>
                    <td>{{ $item->product_name_snapshot }}</td>
                    <td>{{ $item->qty }}</td>
                    <td>{{ \App\Support\Money::fromDecimal((string) $item->unit_price)->format() }}</td>
                    <td>{{ \App\Support\Money::fromDecimal((string) $item->discount_amount)->format() }}</td>
                    <td>{{ \App\Support\Money::fromDecimal((string) $item->line_total)->format() }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr><td colspan="4" style="text-align:right;">{{ __('ui.sales.subtotal') }}</td><td>{{ \App\Support\Money::fromDecimal((string) $sale->subtotal_amount)->format() }}</td></tr>
            <tr><td colspan="4" style="text-align:right;">{{ __('ui.sales.discount') }}</td><td>−{{ \App\Support\Money::fromDecimal((string) $sale->discount_amount)->format() }}</td></tr>
            <tr><td colspan="4" style="text-align:right;">{{ __('ui.sales.tax') }}</td><td>{{ \App\Support\Money::fromDecimal((string) $sale->tax_amount)->format() }}</td></tr>
            <tr><td colspan="4" style="text-align:right;"><strong>{{ __('ui.sales.total') }}</strong></td><td><strong>{{ \App\Support\Money::fromDecimal((string) $sale->total_amount)->format() }}</strong></td></tr>
            <tr><td colspan="4" style="text-align:right;">{{ __('ui.sales.paid') }}</td><td>{{ \App\Support\Money::fromDecimal((string) $sale->paid_amount)->format() }}</td></tr>
            <tr><td colspan="4" style="text-align:right;">{{ __('ui.sales.remaining') }}</td><td>{{ $sale->remaining()->format() }}</td></tr>
        </tfoot>
    </table>

    <p style="margin-top:4mm;">{{ __('ui.sales.pay_status') }}: {{ __('ui.sales.pay_'.$sale->paymentStatus()) }}</p>
</x-print-layout>
