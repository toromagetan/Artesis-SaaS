<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.reports.insurance_usages') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            @include('reporting.partials.tabs')
            @include('reporting.partials.filters')

            <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.reports.date') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.reports.branch') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.reports.sale') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.reports.customer') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.reports.insurance') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ __('ui.reports.transaction_amount') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ __('ui.reports.claim_amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $usage)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3">{{ $usage->used_at->format('d/m/Y') }}</td>
                                <td class="px-4 py-3">{{ $usage->branch?->name }}</td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('sales.show', $usage->sale) }}" class="font-medium text-lentera-800 underline-offset-2 hover:underline">{{ $usage->sale?->number }}</a>
                                </td>
                                <td class="px-4 py-3">{{ $usage->customer?->name }}</td>
                                <td class="px-4 py-3">{{ $usage->insurance?->name }}</td>
                                <td class="px-4 py-3 text-right tnum">{{ \App\Support\Money::fromDecimal((string) $usage->transaction_amount)->format() }}</td>
                                <td class="px-4 py-3 text-right tnum">{{ \App\Support\Money::fromDecimal((string) $usage->insurance_claim_amount)->format() }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><x-lentera.empty-state>{{ __('ui.reports.insurance_usages_empty') }}</x-lentera.empty-state></td></tr>
                        @endforelse
                        <tr class="bg-gray-50 font-medium">
                            <td class="px-4 py-3" colspan="5">TOTAL</td>
                            <td class="px-4 py-3 text-right tnum">{{ $totals['transactions']->format() }}</td>
                            <td class="px-4 py-3 text-right tnum">{{ $totals['claims']->format() }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
