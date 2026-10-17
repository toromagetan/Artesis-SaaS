<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.reports.purchases') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            @include('reporting.partials.tabs')
            @include('reporting.partials.filters')

            <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.reports.supplier') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ __('ui.reports.receipts') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ __('ui.reports.gross') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ __('ui.reports.returns') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ __('ui.reports.net') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 font-medium text-gray-800">{{ $row['supplier_name'] }}</td>
                                <td class="px-4 py-3 text-right tnum">{{ $row['receipts'] }}</td>
                                <td class="px-4 py-3 text-right tnum">{{ $row['gross']->format() }}</td>
                                <td class="px-4 py-3 text-right tnum">{{ $row['returns']->format() }}</td>
                                <td class="px-4 py-3 text-right font-medium tnum">{{ $row['net']->format() }}</td>
                            </tr>
                        @endforeach
                        <tr class="bg-gray-50 font-medium">
                            <td class="px-4 py-3">TOTAL</td>
                            <td class="px-4 py-3 text-right tnum">{{ array_sum(array_column($rows, 'receipts')) }}</td>
                            <td class="px-4 py-3 text-right tnum">{{ $gross->format() }}</td>
                            <td class="px-4 py-3 text-right tnum">{{ $returns->format() }}</td>
                            <td class="px-4 py-3 text-right tnum">{{ $net->format() }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
