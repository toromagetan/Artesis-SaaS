<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.periods.title') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
            <p class="mb-4 text-sm text-gray-600">{{ __('ui.periods.intro') }}</p>

            <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.periods.period') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.periods.status') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.periods.locked_by') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.periods.unlock_info') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 font-medium tnum">{{ $row['period'] }}</td>
                                <td class="px-4 py-3">
                                    @if ($row['locked'])
                                        <x-lentera.badge tone="red">{{ __('ui.periods.locked') }}</x-lentera.badge>
                                    @else
                                        <x-lentera.badge tone="green">{{ __('ui.periods.open') }}</x-lentera.badge>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-600">
                                    @if ($row['ever_locked'])
                                        {{ $row['locked_by'] }}@if ($row['locked_at']) · {{ \Carbon\Carbon::parse($row['locked_at'])->format('d/m/Y H:i') }}@endif
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-600">
                                    @if ($row['unlocked_by'])
                                        {{ $row['unlocked_by'] }} — {{ $row['unlock_reason'] }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @if ($row['locked'])
                                        @can('periods.unlock')
                                            <form method="POST" action="{{ route('periods.unlock') }}" class="flex items-center justify-end gap-2">
                                                @csrf
                                                <input type="hidden" name="period" value="{{ $row['period'] }}">
                                                <x-text-input name="reason" class="w-44" :placeholder="__('ui.periods.reason_placeholder')" required maxlength="255" />
                                                <x-secondary-button type="submit">{{ __('ui.periods.unlock_action') }}</x-secondary-button>
                                            </form>
                                        @endcan
                                    @else
                                        @can('periods.lock')
                                            <form method="POST" action="{{ route('periods.lock') }}" onsubmit="return confirm('{{ __('ui.periods.lock_confirm', ['period' => $row['period']]) }}')">
                                                @csrf
                                                <input type="hidden" name="period" value="{{ $row['period'] }}">
                                                <x-secondary-button type="submit">{{ __('ui.periods.lock_action') }}</x-secondary-button>
                                            </form>
                                        @endcan
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
