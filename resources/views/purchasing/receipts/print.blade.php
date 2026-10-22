<x-print-layout :title="__('ui.print_doc.grn_doc')" :store-name="$storeName" :printed-by="$printedBy" paper="A5 portrait">
    <h1 style="font-size:14pt; margin:0 0 2mm;">{{ __('ui.print_doc.grn_doc') }} — {{ $receipt->number }}</h1>
    <p style="margin:0 0 4mm;">
        {{ __('ui.purchasing.number') }} PO: {{ $receipt->purchaseOrder->number }} ·
        {{ __('ui.purchasing.supplier') }}: {{ $receipt->purchaseOrder->supplier->name }} ·
        {{ __('ui.purchasing.branch') }}: {{ $receipt->purchaseOrder->branch->name }}
    </p>
    <p style="margin:0 0 4mm;">
        {{ __('ui.purchasing.received_date') }}: {{ $receipt->received_date->format('d/m/Y') }} ·
        {{ __('ui.purchasing.delivery_note') }}: {{ $receipt->delivery_note_no ?? '—' }} ·
        {{ __('ui.print_doc.received_by') }}: {{ $receipt->receiver?->name }}
    </p>

    <table style="width:100%; border-collapse:collapse;" border="1" cellpadding="4">
        <thead>
            <tr>
                <th>{{ __('ui.purchasing.product') }}</th>
                <th>{{ __('ui.purchasing.qty_ordered') }}</th>
                <th>{{ __('ui.purchasing.qty_received') }}</th>
                <th>{{ __('ui.purchasing.unit_cost_actual') }}</th>
                <th>{{ __('ui.purchasing.line_total') }}</th>
            </tr>
        </thead>
        <tbody>
            @php $total = 0; @endphp
            @foreach ($receipt->items as $item)
                @php $line = $item->qty * (float) $item->unit_cost_actual; $total += $line; @endphp
                <tr>
                    <td>{{ $item->purchaseOrderItem->product->name }}</td>
                    <td>{{ $item->purchaseOrderItem->qty }}</td>
                    <td>{{ $item->qty }}</td>
                    <td>{{ \App\Support\Money::fromDecimal((string) $item->unit_cost_actual)->format() }}</td>
                    <td>{{ \App\Support\Money::fromDecimal((string) $line)->format() }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr><td colspan="4" style="text-align:right;"><strong>{{ __('ui.purchasing.total') }}</strong></td><td><strong>{{ \App\Support\Money::fromDecimal((string) $total)->format() }}</strong></td></tr>
        </tfoot>
    </table>
</x-print-layout>
