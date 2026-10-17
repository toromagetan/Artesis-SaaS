<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.reports.payables') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            @include('reporting.partials.tabs')
            @include('reporting.partials.filters')

            @forelse ($suppliers as $supplier)
                <div class="mb-6 overflow-hidden rounded-lg bg-white shadow-soft">
                    <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3">
                        <h2 class="text-sm font-semibold text-gray-800">{{ $supplier['supplier_name'] }}</h2>
                        <div class="text-sm">
                            <span class="text-gray-500">{{ __('ui.reports.remaining') }}:</span>
                            <span class="font-semibold tnum">{{ $supplier['total']->format() }}</span>
                            @if ($supplier['credit']->isPositive())
                                <span class="ms-4 text-gray-500">{{ __('ui.reports.supplier_credit') }}:</span>
                                <span class="font-semibold tnum">{{ $supplier['credit']->format() }}</span>
                            @endif
                        </div>
                    </div>
                    @if (count($supplier['receipts']) > 0)
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-left text-gray-500">
                                    <th class="px-4 py-2 font-medium">{{ __('ui.reports.receipt_number') }}</th>
                                    <th class="px-4 py-2 font-medium">PO</th>
                                    <th class="px-4 py-2 font-medium">{{ __('ui.reports.date') }}</th>
                                    <th class="px-4 py-2 text-right font-medium">{{ __('ui.reports.total') }}</th>
                                    <th class="px-4 py-2 text-right font-medium">{{ __('ui.reports.allocated') }}</th>
                                    <th class="px-4 py-2 text-right font-medium">{{ __('ui.reports.remaining') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($supplier['receipts'] as $receipt)
                                    <tr class="border-t border-gray-50">
                                        <td class="px-4 py-2 font-medium">{{ $receipt['number'] }}</td>
                                        <td class="px-4 py-2">{{ $receipt['po_number'] }}</td>
                                        <td class="px-4 py-2">{{ \Illuminate\Support\Carbon::parse($receipt['date'])->format('d/m/Y') }}</td>
                                        <td class="px-4 py-2 text-right tnum">{{ $receipt['total']->format() }}</td>
                                        <td class="px-4 py-2 text-right tnum">{{ $receipt['allocated']->format() }}</td>
                                        <td class="px-4 py-2 text-right font-medium tnum">{{ $receipt['remaining']->format() }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            @empty
                <div class="rounded-lg bg-white shadow-soft"><x-lentera.empty-state>{{ __('ui.purchasing.no_outstanding') }}</x-lentera.empty-state></div>
            @endforelse

            <div class="rounded-lg bg-lentera-50 px-4 py-3 text-right text-sm">
                <span class="text-gray-600">{{ __('ui.reports.grand_total') }}:</span>
                <span class="font-semibold tnum">{{ $grandTotal->format() }}</span>
            </div>
        </div>
    </div>
</x-app-layout>
