<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.financial.income_statement') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
            @include('reporting.partials.tabs')
            @include('accounting.financial.tabs')
            @include('reporting.partials.filters')

            <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <tbody>
                        <tr class="border-b border-gray-100 bg-gray-50">
                            <td colspan="2" class="px-4 py-3 font-semibold text-gray-800">{{ __('ui.financial.revenue') }}</td>
                        </tr>
                        @forelse ($statement['revenues'] as $row)
                            <tr class="border-b border-gray-50">
                                <td class="px-4 py-2.5"><span class="text-gray-500 tnum">{{ $row['account']->code }}</span> {{ $row['account']->name }}</td>
                                <td class="px-4 py-2.5 text-right tnum">{{ $row['balance']->format() }}</td>
                            </tr>
                        @empty
                            <tr class="border-b border-gray-50"><td colspan="2" class="px-4 py-3 text-gray-500">—</td></tr>
                        @endforelse
                        <tr class="border-b border-gray-100 font-medium">
                            <td class="px-4 py-3">{{ __('ui.financial.total_revenue') }}</td>
                            <td class="px-4 py-3 text-right tnum">{{ $statement['total_revenue']->format() }}</td>
                        </tr>
                        <tr class="border-b border-gray-100 bg-gray-50">
                            <td colspan="2" class="px-4 py-3 font-semibold text-gray-800">{{ __('ui.financial.expenses') }}</td>
                        </tr>
                        @forelse ($statement['expenses'] as $row)
                            <tr class="border-b border-gray-50">
                                <td class="px-4 py-2.5"><span class="text-gray-500 tnum">{{ $row['account']->code }}</span> {{ $row['account']->name }}</td>
                                <td class="px-4 py-2.5 text-right tnum">{{ $row['balance']->format() }}</td>
                            </tr>
                        @empty
                            <tr class="border-b border-gray-50"><td colspan="2" class="px-4 py-3 text-gray-500">—</td></tr>
                        @endforelse
                        <tr class="border-b border-gray-100 font-medium">
                            <td class="px-4 py-3">{{ __('ui.financial.total_expenses') }}</td>
                            <td class="px-4 py-3 text-right tnum">{{ $statement['total_expense']->format() }}</td>
                        </tr>
                        <tr class="bg-lentera-50 font-semibold text-lentera-900">
                            <td class="px-4 py-3">{{ __('ui.financial.net_income') }}</td>
                            <td class="px-4 py-3 text-right tnum">{{ $statement['net']->format() }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-6 overflow-hidden rounded-lg bg-white shadow-soft">
                <div class="border-b border-gray-100 px-4 py-3 font-semibold text-gray-800">{{ __('ui.financial.per_branch') }}</div>
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.reports.branch') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ __('ui.financial.revenue') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ __('ui.financial.expenses') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ __('ui.financial.net_income') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($byBranch as $row)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 font-medium text-gray-800">{{ $row['branch_id'] === null ? __('ui.financial.no_branch') : ($branches->firstWhere('id', $row['branch_id'])?->name ?? '#'.$row['branch_id']) }}</td>
                                <td class="px-4 py-3 text-right tnum">{{ $row['revenue']->format() }}</td>
                                <td class="px-4 py-3 text-right tnum">{{ $row['expense']->format() }}</td>
                                <td class="px-4 py-3 text-right font-medium tnum">{{ $row['net']->format() }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-3 text-gray-500">—</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
