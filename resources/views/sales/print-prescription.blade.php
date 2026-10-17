<x-print-layout :title="__('ui.prescriptions.detail')" :store-name="$storeName" :paper="'A5 portrait'">
    <h1 style="font-size:14pt; margin:0 0 2mm;">{{ __('ui.prescriptions.detail') }} — {{ $sale->number }}</h1>
    @if ($snapshot)
        <p style="margin:0 0 4mm;">{{ $sale->customer->name }} · {{ __('ui.prescriptions.examined_at') }}: {{ \Illuminate\Support\Carbon::parse($snapshot['examined_at'])->format('d/m/Y') }} · {{ $sale->branch->name }}</p>
        <table style="width:100%; border-collapse:collapse;" border="1" cellpadding="4">
            <thead>
                <tr><th></th><th>{{ __('ui.prescriptions.sph') }}</th><th>{{ __('ui.prescriptions.cyl') }}</th><th>{{ __('ui.prescriptions.axis') }}</th><th>{{ __('ui.prescriptions.add') }}</th><th>PD</th></tr>
            </thead>
            <tbody>
                <tr><td>OD</td><td>{{ $snapshot['od']['sph'] }}</td><td>{{ $snapshot['od']['cyl'] }}</td><td>{{ $snapshot['od']['axis'] ?? '—' }}</td><td>{{ $snapshot['od']['add'] ?? '—' }}</td><td>{{ $snapshot['od']['pd'] ?? '—' }}</td></tr>
                <tr><td>OS</td><td>{{ $snapshot['os']['sph'] }}</td><td>{{ $snapshot['os']['cyl'] }}</td><td>{{ $snapshot['os']['axis'] ?? '—' }}</td><td>{{ $snapshot['os']['add'] ?? '—' }}</td><td>{{ $snapshot['os']['pd'] ?? '—' }}</td></tr>
            </tbody>
        </table>
        <p style="margin-top:4mm;">{{ __('ui.prescriptions.pd_total') }}: {{ $snapshot['pd_total'] ?? '—' }} · {{ __('ui.prescriptions.prism') }}: {{ $snapshot['prism'] ?: '—' }}</p>
    @else
        <p>{{ __('ui.sales.prescription_none') }}</p>
    @endif
</x-print-layout>
