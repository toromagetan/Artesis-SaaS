<x-print-layout :title="__('ui.purchasing.po_print')" :store-name="$storeName" :paper="'A5 landscape'">
    <h1 style="font-size:14pt; margin:0 0 2mm;">{{ __('ui.purchasing.po_print') }} — {{ $po->number }}</h1>
    <p style="margin:0 0 4mm;">{{ __('ui.purchasing.supplier') }}: {{ $po->supplier->name }} · {{ __('ui.purchasing.date') }}: {{ $po->order_date->format('d/m/Y') }} · {{ __('ui.purchasing.destination_branch') }}: {{ $po->branch->name }}</p>

    <table style="width:100%; border-collapse:collapse;" border="1" cellpadding="4">
        <thead>
            <tr>
                <th>{{ __('ui.purchasing.product') }}</th>
                <th>{{ __('ui.purchasing.qty') }}</th>
                <th>{{ __('ui.purchasing.unit_cost') }}</th>
                <th>{{ __('ui.purchasing.line_total') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($po->items as $item)
                <tr>
                    <td>{{ $item->product->name }} ({{ $item->product->sku }})</td>
                    <td>{{ $item->qty_ordered }}</td>
                    <td>{{ \App\Support\Money::fromDecimal((string) $item->unit_cost)->format() }}</td>
                    <td>{{ \App\Support\Money::fromDecimal((string) $item->line_total)->format() }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr><td colspan="3" style="text-align:right;"><strong>{{ __('ui.purchasing.total') }}</strong></td><td><strong>{{ \App\Support\Money::fromDecimal((string) $po->total_amount)->format() }}</strong></td></tr>
        </tfoot>
    </table>
</x-print-layout>
