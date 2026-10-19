<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.financial.cash_flow') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
            @include('reporting.partials.tabs')
            @include('accounting.financial.tabs')

            <form method="GET" class="mb-4 flex flex-wrap items-end gap-2">
                <div>
                    <x-input-label for="dari" :value="__('ui.reports.from')" />
                    <x-text-input id="dari" name="dari" type="date" value="{{ $from }}" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="sampai" :value="__('ui.reports.to')" />
                    <x-text-input id="sampai" name="sampai" type="date" value="{{ $to }}" class="mt-1" />
                </div>
                <x-secondary-button type="submit">{{ __('ui.reports.apply') }}</x-secondary-button>
                <x-lentera.dropdown-cetak>
                    <x-lentera.menu-item :href="url()->current().'?'.http_build_query(array_merge(request()->query(), ['ekspor' => 1]))">{{ __('ui.reports.export_csv') }}</x-lentera.menu-item>
                    <x-lentera.menu-item :href="url()->current().'?'.http_build_query(array_merge(request()->query(), ['format' => 'xlsx']))">{{ __('ui.reports.export_xlsx') }}</x-lentera.menu-item>
                </x-lentera.dropdown-cetak>
            </form>

            <p class="mb-4 text-sm text-gray-600">{{ __('ui.financial.cash_flow_note') }}</p>

            <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <tbody>
                        <tr class="border-b border-gray-100 font-medium">
                            <td class="px-4 py-3">{{ __('ui.financial.opening') }}</td>
                            <td class="px-4 py-3 text-right tnum">{{ $flow['opening']->format() }}</td>
                        </tr>
                        @forelse ($flow['groups'] as $group)
                            <tr class="border-b border-gray-50">
                                <td class="px-4 py-2.5 text-gray-700">{{ $group['source_type'] }}</td>
                                <td class="px-4 py-2.5 text-right tnum">{{ $group['amount']->format() }}</td>
                            </tr>
                        @empty
                            <tr class="border-b border-gray-50"><td colspan="2" class="px-4 py-3 text-gray-500">—</td></tr>
                        @endforelse
                        <tr class="bg-lentera-50 font-semibold text-lentera-900">
                            <td class="px-4 py-3">{{ __('ui.financial.closing') }}</td>
                            <td class="px-4 py-3 text-right tnum">{{ $flow['closing']->format() }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
