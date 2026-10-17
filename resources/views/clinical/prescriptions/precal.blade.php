<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.precal.title') }} — {{ $prescription->customer->name }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
            <div class="mb-4 flex items-center justify-between">
                <a href="{{ route('prescriptions.show', $prescription) }}" class="text-sm text-gray-600 underline">{{ __('ui.prescriptions.detail') }}</a>
                <x-lentera.dropdown-cetak>
                    <x-lentera.menu-item :href="route('prescriptions.precal-print', $prescription)" target="_blank">{{ __('ui.precal.print') }}</x-lentera.menu-item>
                </x-lentera.dropdown-cetak>
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.precal.parameter') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.prescriptions.eye_right') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.prescriptions.eye_left') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (__('ui.precal.parameters') as $key => $label)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 text-gray-500">{{ $label }}</td>
                                <td class="px-4 py-3 font-medium text-gray-800 tnum">
                                    @if ($key === 'rx_feasibility')
                                        {{ __('ui.precal.'.$precal['od'][$key]) }}
                                    @elseif ($key === 'pair_summary')
                                        {{ __('ui.precal.'.$precal['od'][$key]) }}
                                    @else
                                        {{ $precal['od'][$key] ?? '—' }}
                                    @endif
                                </td>
                                <td class="px-4 py-3 font-medium text-gray-800 tnum">
                                    @if ($key === 'rx_feasibility')
                                        {{ __('ui.precal.'.$precal['os'][$key]) }}
                                    @elseif ($key === 'pair_summary')
                                        {{ __('ui.precal.'.$precal['os'][$key]) }}
                                    @else
                                        {{ $precal['os'][$key] ?? '—' }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <p class="mt-4 text-xs text-gray-400">{{ __('ui.precal.estimation_note', ['index' => $precal['assumptions']['lens_index'], 'blank' => $precal['assumptions']['blank_diameter_mm'], 'frame' => $precal['assumptions']['frame_center_distance_mm']]) }}</p>
        </div>
    </div>
</x-app-layout>
