<x-print-layout :title="__('ui.prescriptions.detail')" :store-name="$storeName" :paper="'A5 portrait'">
    <h1 style="font-size:14pt; margin:0 0 2mm;">{{ __('ui.prescriptions.detail') }}</h1>
    <p style="margin:0 0 4mm;">{{ $prescription->customer->name }} · {{ __('ui.prescriptions.examined_at') }}: {{ $prescription->examined_at->format('d/m/Y') }} · {{ $prescription->branch->name }}</p>

    <table style="width:100%; border-collapse:collapse;" border="1" cellpadding="4">
        <thead>
            <tr>
                <th></th>
                <th>{{ __('ui.prescriptions.sph') }}</th>
                <th>{{ __('ui.prescriptions.cyl') }}</th>
                <th>{{ __('ui.prescriptions.axis') }}</th>
                <th>{{ __('ui.prescriptions.add') }}</th>
                <th>PD</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>OD</td>
                <td>{{ $prescription->od_sph }}</td>
                <td>{{ $prescription->od_cyl }}</td>
                <td>{{ $prescription->od_axis ?? '—' }}</td>
                <td>{{ $prescription->od_add ?? '—' }}</td>
                <td>{{ $prescription->od_pdr ?? '—' }}</td>
            </tr>
            <tr>
                <td>OS</td>
                <td>{{ $prescription->os_sph }}</td>
                <td>{{ $prescription->os_cyl }}</td>
                <td>{{ $prescription->os_axis ?? '—' }}</td>
                <td>{{ $prescription->os_add ?? '—' }}</td>
                <td>{{ $prescription->os_pdl ?? '—' }}</td>
            </tr>
        </tbody>
    </table>

    <p style="margin-top:4mm;">{{ __('ui.prescriptions.pd_total') }}: {{ $prescription->pd_total ?? '—' }} · {{ __('ui.prescriptions.prism') }}: {{ $prescription->prism ?: '—' }} · {{ __('ui.prescriptions.examiner') }}: {{ $prescription->examiner?->name ?: '—' }}</p>
</x-print-layout>
