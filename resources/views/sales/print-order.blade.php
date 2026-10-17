<x-print-layout :title="__('ui.sales.order_doc')" :store-name="$storeName" :paper="'A5 portrait'">
    <h1 style="font-size:14pt; margin:0 0 2mm;">{{ __('ui.sales.order_doc') }} — {{ $sale->number }}</h1>
    <p style="margin:0 0 4mm;">{{ $sale->customer->name }} · {{ $sale->customer->phone }} · {{ $sale->sale_date->format('d/m/Y') }} · {{ $sale->branch->name }} · {{ __('ui.sales.cashier') }}: {{ $sale->cashier->name }}</p>

    <table style="width:100%; border-collapse:collapse;" border="1" cellpadding="4">
        <thead>
            <tr>
                <th>{{ __('ui.sales.item') }}</th>
                <th>{{ __('ui.sales.qty') }}</th>
                <th>{{ __('ui.sales.rx_ref') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($sale->items as $item)
                <tr>
                    <td>{{ $item->product_name_snapshot }}</td>
                    <td>{{ $item->qty }}</td>
                    <td>{{ $item->prescription_ref ? strtoupper($item->prescription_ref) : '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($sale->prescription_snapshot)
        <h2 style="font-size:11pt; margin:5mm 0 2mm;">{{ __('ui.sales.prescription') }}</h2>
        <table style="width:100%; border-collapse:collapse;" border="1" cellpadding="4">
            <thead>
                <tr><th></th><th>{{ __('ui.prescriptions.sph') }}</th><th>{{ __('ui.prescriptions.cyl') }}</th><th>{{ __('ui.prescriptions.axis') }}</th><th>{{ __('ui.prescriptions.add') }}</th><th>PD</th></tr>
            </thead>
            <tbody>
                <tr><td>OD</td><td>{{ $sale->prescription_snapshot['od']['sph'] }}</td><td>{{ $sale->prescription_snapshot['od']['cyl'] }}</td><td>{{ $sale->prescription_snapshot['od']['axis'] ?? '—' }}</td><td>{{ $sale->prescription_snapshot['od']['add'] ?? '—' }}</td><td>{{ $sale->prescription_snapshot['od']['pd'] ?? '—' }}</td></tr>
                <tr><td>OS</td><td>{{ $sale->prescription_snapshot['os']['sph'] }}</td><td>{{ $sale->prescription_snapshot['os']['cyl'] }}</td><td>{{ $sale->prescription_snapshot['os']['axis'] ?? '—' }}</td><td>{{ $sale->prescription_snapshot['os']['add'] ?? '—' }}</td><td>{{ $sale->prescription_snapshot['os']['pd'] ?? '—' }}</td></tr>
            </tbody>
        </table>
    @endif

    @if ($sale->notes)
        <p style="margin-top:4mm;">{{ __('ui.sales.notes') }}: {{ $sale->notes }}</p>
    @endif
</x-print-layout>
