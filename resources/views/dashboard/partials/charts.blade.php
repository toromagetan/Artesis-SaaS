@isset($charts)
    <div class="grid gap-4 lg:grid-cols-3">
        <div class="rounded-lg bg-white p-6 shadow-soft lg:col-span-2">
            <p class="text-sm font-medium text-gray-700">{{ __('ui.dashboard.trend_title') }}</p>
            <p class="mb-3 text-xs text-gray-400">{{ __('ui.dashboard.trend_sub') }}</p>
            {!! $charts['trend_svg'] !!}
        </div>
        <div class="rounded-lg bg-white p-6 shadow-soft">
            <p class="text-sm font-medium text-gray-700">{{ __('ui.dashboard.composition_title') }}</p>
            <p class="mb-3 text-xs text-gray-400">{{ __('ui.dashboard.composition_sub') }}</p>
            <div class="mx-auto max-w-[220px]">{!! $charts['composition_svg'] !!}</div>
            @if ($charts['legend'] === [])
                <p class="mt-3 text-center text-sm text-gray-400">{{ __('ui.dashboard.no_data') }}</p>
            @else
                <ul class="mt-3 space-y-1.5 text-sm">
                    @foreach ($charts['legend'] as $row)
                        <li class="flex items-center justify-between gap-2">
                            <span class="flex items-center gap-2 text-gray-600">
                                <span class="inline-block h-2.5 w-2.5 rounded-full" style="background-color: {{ $row['color'] }}"></span>{{ $row['label'] }}
                            </span>
                            <span class="tnum text-gray-500">{{ $row['value']->format() }} · {{ number_format($row['pct'], 1, ',', '.') }}%</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
@endisset
