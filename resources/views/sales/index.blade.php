<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.sales.title') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-lg border border-lentera-200 bg-lentera-50 px-4 py-3 text-sm text-lentera-800">{{ session('status') }}</div>
            @endif

            <div class="mb-4 flex items-center justify-between">
                <form method="GET" class="flex gap-2">
                    <x-text-input name="q" :value="$q" :placeholder="__('ui.sales.search_placeholder')" class="w-72" />
                    <select name="cabang" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                        <option value="">{{ __('ui.sales.branch') }}</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected((string) $branchId === (string) $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                    <select name="status_bayar" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                        <option value="">{{ __('ui.sales.pay_status') }}</option>
                        <option value="paid" @selected($payStatus === 'paid')>{{ __('ui.sales.pay_paid') }}</option>
                        <option value="partial" @selected($payStatus === 'partial')>{{ __('ui.sales.pay_partial') }}</option>
                        <option value="unpaid" @selected($payStatus === 'unpaid')>{{ __('ui.sales.pay_unpaid') }}</option>
                        <option value="void" @selected($payStatus === 'void')>{{ __('ui.sales.status_void') }}</option>
                    </select>
                    <x-secondary-button type="submit">{{ __('ui.search') }}</x-secondary-button>
                </form>
                @can('sales.create')
                    <x-lentera.aksi-bar>
                        <x-lentera.menu-item :href="route('sales.create')" primary>{{ __('ui.sales.create') }}</x-lentera.menu-item>
                    </x-lentera.aksi-bar>
                @endcan
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.sales.number') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.sales.date') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.sales.customer') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.sales.branch') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.sales.total') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.sales.paid') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.sales.pay_status') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.sales.status') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($sales as $sale)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 font-medium text-gray-800">
                                    <a href="{{ route('sales.show', $sale) }}" class="text-lentera-800 underline-offset-2 hover:underline">{{ $sale->number }}</a>
                                </td>
                                <td class="px-4 py-3">{{ $sale->sale_date->format('d/m/Y') }}</td>
                                <td class="px-4 py-3">{{ $sale->customer->name }}</td>
                                <td class="px-4 py-3">{{ $sale->branch->name }}</td>
                                <td class="px-4 py-3 tnum">{{ \App\Support\Money::fromDecimal((string) $sale->total_amount)->format() }}</td>
                                <td class="px-4 py-3 tnum">{{ \App\Support\Money::fromDecimal((string) $sale->paid_amount)->format() }}</td>
                                <td class="px-4 py-3">{{ __('ui.sales.pay_'.$sale->paymentStatus()) }}</td>
                                <td class="px-4 py-3">
                                    @if ($sale->isVoid())
                                        <span class="inline-flex items-center rounded-full bg-red-50 px-2 py-0.5 text-xs font-semibold text-red-600">{{ __('ui.sales.status_void') }}</span>
                                    @else
                                        <x-lentera.badge :active="true" />
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <x-lentera.aksi-baris>
                                        <x-lentera.menu-item :href="route('sales.show', $sale)" sm>{{ __('ui.sales.detail') }}</x-lentera.menu-item>
                                        @can('sales.pay')
                                            @unless ($sale->isVoid() || $sale->paymentStatus() === 'paid')
                                                <x-lentera.menu-item :href="route('sales.payment', $sale)" sm>{{ __('ui.sales.add_payment') }}</x-lentera.menu-item>
                                            @endunless
                                        @endcan
                                    </x-lentera.aksi-baris>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9"><x-lentera.empty-state>{{ __('ui.sales.empty') }}</x-lentera.empty-state></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $sales->links() }}</div>
        </div>
    </div>
</x-app-layout>
