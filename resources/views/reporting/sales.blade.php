<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.reports.sales') }}</h1>
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
                            <th class="px-4 py-3 text-right font-medium">{{ __('ui.reports.transactions') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ __('ui.reports.total') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ __('ui.reports.returns') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ __('ui.reports.net') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ __('ui.reports.paid') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ __('ui.reports.receivable') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 font-medium text-gray-800">{{ \App\Modules\Master\Models\Branch::find($row['branch_id'])?->name }}</td>
                                <td class="px-4 py-3 text-right tnum">{{ $row['transactions'] }}</td>
                                <td class="px-4 py-3 text-right tnum">{{ $row['total']->format() }}</td>
                                <td class="px-4 py-3 text-right tnum">{{ $row['returns']->format() }}</td>
                                <td class="px-4 py-3 text-right font-medium tnum">{{ $row['net']->format() }}</td>
                                <td class="px-4 py-3 text-right tnum">{{ $row['paid']->format() }}</td>
                                <td class="px-4 py-3 text-right tnum">{{ $row['receivable']->format() }}</td>
                            </tr>
                        @endforeach
                        <tr class="bg-gray-50 font-medium">
                            <td class="px-4 py-3">TOTAL</td>
                            <td class="px-4 py-3 text-right tnum">{{ $totals['transactions'] }}</td>
                            <td class="px-4 py-3 text-right tnum">{{ $totals['total']->format() }}</td>
                            <td class="px-4 py-3 text-right tnum">{{ $totals['returns']->format() }}</td>
                            <td class="px-4 py-3 text-right tnum">{{ $totals['net']->format() }}</td>
                            <td class="px-4 py-3 text-right tnum">{{ $totals['paid']->format() }}</td>
                            <td class="px-4 py-3 text-right tnum">{{ $totals['receivable']->format() }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
