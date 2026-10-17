<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.reports.stock') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            @include('reporting.partials.tabs')
            @include('reporting.partials.filters')

            <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.reports.branch') }}</th>
                            <th class="px-4 py-3 font-medium">SKU</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.reports.product') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ __('ui.reports.qty') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ __('ui.reports.in_transit') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ __('ui.reports.value') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3">{{ \App\Modules\Master\Models\Branch::find($row['branch_id'])?->name }}</td>
                                <td class="px-4 py-3 tnum">{{ $row['sku'] }}</td>
                                <td class="px-4 py-3 font-medium text-gray-800">{{ $row['product_name'] }}</td>
                                <td class="px-4 py-3 text-right tnum">{{ $row['qty'] }}</td>
                                <td class="px-4 py-3 text-right tnum">{{ $row['in_transit'] }}</td>
                                <td class="px-4 py-3 text-right tnum">{{ $row['value']->format() }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><x-lentera.empty-state>{{ __('ui.stock.empty') }}</x-lentera.empty-state></td></tr>
                        @endforelse
                        <tr class="bg-gray-50 font-medium">
                            <td class="px-4 py-3" colspan="5">TOTAL {{ __('ui.reports.value') }}</td>
                            <td class="px-4 py-3 text-right tnum">{{ $totalValue->format() }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
