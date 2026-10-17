<x-print-layout :title="__('ui.precal.title')" :store-name="$storeName" :paper="'A5 portrait'">
    <h1 style="font-size:14pt; margin:0 0 2mm;">{{ __('ui.precal.title') }}</h1>
    <p style="margin:0 0 4mm;">{{ $prescription->customer->name }} · {{ __('ui.prescriptions.examined_at') }}: {{ $prescription->examined_at->format('d/m/Y') }} · {{ $prescription->branch->name }}</p>

    <table style="width:100%; border-collapse:collapse;" border="1" cellpadding="4">
        <thead>
            <tr>
                <th>{{ __('ui.precal.parameter') }}</th>
                <th>OD</th>
                <th>OS</th>
            </tr>
        </thead>
        <tbody>
            @foreach (__('ui.precal.parameters') as $key => $label)
                <tr>
                    <td>{{ $label }}</td>
                    <td>@if ($key === 'rx_feasibility' || $key === 'pair_summary'){{ __('ui.precal.'.$precal['od'][$key]) }}@else{{ $precal['od'][$key] ?? '—' }}@endif</td>
                    <td>@if ($key === 'rx_feasibility' || $key === 'pair_summary'){{ __('ui.precal.'.$precal['os'][$key]) }}@else{{ $precal['os'][$key] ?? '—' }}@endif</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p style="margin-top:4mm; font-size:8pt;">{{ __('ui.precal.estimation_note', ['index' => $precal['assumptions']['lens_index'], 'blank' => $precal['assumptions']['blank_diameter_mm'], 'frame' => $precal['assumptions']['frame_center_distance_mm']]) }}</p>
</x-print-layout>
