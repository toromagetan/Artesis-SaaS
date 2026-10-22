<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.financial.balance_sheet') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
            @include('reporting.partials.tabs')
            @include('accounting.financial.tabs')

            <form method="GET" class="mb-4 flex flex-wrap items-end gap-2">
                <div>
                    <x-input-label for="per" :value="__('ui.financial.as_of')" />
                    <x-text-input id="per" name="per" type="date" value="{{ $asOf }}" class="mt-1" />
                </div>
                <x-secondary-button type="submit">{{ __('ui.reports.apply') }}</x-secondary-button>
                <x-lentera.aksi-bar>
                    <x-lentera.menu-item :href="url()->current().'?'.http_build_query(array_merge(request()->query(), ['ekspor' => 1]))" print>{{ __('ui.reports.export_csv') }}</x-lentera.menu-item>
                    <x-lentera.menu-item :href="url()->current().'?'.http_build_query(array_merge(request()->query(), ['format' => 'xlsx']))" print>{{ __('ui.reports.export_xlsx') }}</x-lentera.menu-item>
                </x-lentera.aksi-bar>
            </form>

            @if ($sheet['balanced'])
                <p class="mb-4 rounded-lg border border-lentera-200 bg-lentera-50 px-4 py-2 text-sm text-lentera-800">{{ __('ui.financial.balanced') }}</p>
            @else
                <p class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700">{{ __('ui.financial.not_balanced') }}</p>
            @endif

            <div class="grid gap-4 lg:grid-cols-2">
                <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                    <div class="border-b border-gray-100 px-4 py-3 font-semibold text-gray-800">{{ __('ui.financial.assets') }}</div>
                    <table class="min-w-full text-sm">
                        <tbody>
                            @forelse ($sheet['assets'] as $row)
                                <tr class="border-b border-gray-50">
                                    <td class="px-4 py-2.5"><span class="text-gray-500 tnum">{{ $row['account']->code }}</span> {{ $row['account']->name }}</td>
                                    <td class="px-4 py-2.5 text-right tnum">{{ $row['balance']->format() }}</td>
                                </tr>
                            @empty
                                <tr><td class="px-4 py-3 text-gray-500">—</td></tr>
                            @endforelse
                            <tr class="bg-gray-50 font-semibold">
                                <td class="px-4 py-3">{{ __('ui.financial.total_assets') }}</td>
                                <td class="px-4 py-3 text-right tnum">{{ $sheet['total_assets']->format() }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="space-y-4">
                    <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                        <div class="border-b border-gray-100 px-4 py-3 font-semibold text-gray-800">{{ __('ui.financial.liabilities') }}</div>
                        <table class="min-w-full text-sm">
                            <tbody>
                                @forelse ($sheet['liabilities'] as $row)
                                    <tr class="border-b border-gray-50">
                                        <td class="px-4 py-2.5"><span class="text-gray-500 tnum">{{ $row['account']->code }}</span> {{ $row['account']->name }}</td>
                                        <td class="px-4 py-2.5 text-right tnum">{{ $row['balance']->format() }}</td>
                                    </tr>
                                @empty
                                    <tr><td class="px-4 py-3 text-gray-500">—</td></tr>
                                @endforelse
                                <tr class="bg-gray-50 font-semibold">
                                    <td class="px-4 py-3">{{ __('ui.financial.total_liabilities') }}</td>
                                    <td class="px-4 py-3 text-right tnum">{{ $sheet['total_liabilities']->format() }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                        <div class="border-b border-gray-100 px-4 py-3 font-semibold text-gray-800">{{ __('ui.financial.equity') }}</div>
                        <table class="min-w-full text-sm">
                            <tbody>
                                @foreach ($sheet['equity'] as $row)
                                    <tr class="border-b border-gray-50">
                                        <td class="px-4 py-2.5"><span class="text-gray-500 tnum">{{ $row['account']->code }}</span> {{ $row['account']->name }}</td>
                                        <td class="px-4 py-2.5 text-right tnum">{{ $row['balance']->format() }}</td>
                                    </tr>
                                @endforeach
                                <tr class="border-b border-gray-50">
                                    <td class="px-4 py-2.5">{{ __('ui.financial.current_earnings') }}</td>
                                    <td class="px-4 py-2.5 text-right tnum">{{ $sheet['earnings']->format() }}</td>
                                </tr>
                                <tr class="bg-gray-50 font-semibold">
                                    <td class="px-4 py-3">{{ __('ui.financial.total_equity') }}</td>
                                    <td class="px-4 py-3 text-right tnum">{{ $sheet['total_equity']->add($sheet['earnings'])->format() }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
