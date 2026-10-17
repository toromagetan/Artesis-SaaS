<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.purchasing.po_title') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-lg border border-lentera-200 bg-lentera-50 px-4 py-3 text-sm text-lentera-800">{{ session('status') }}</div>
            @endif
            <div class="mb-4 flex items-center justify-between">
                <form method="GET" class="flex gap-2">
                    <x-text-input name="q" :value="$q" :placeholder="__('ui.purchasing.search_placeholder')" class="w-64" />
                    <select name="status" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                        <option value="">{{ __('ui.purchasing.status') }}</option>
                        @foreach (\App\Modules\Purchasing\Models\PurchaseOrder::STATUSES as $s)
                            <option value="{{ $s }}" @selected($status === $s)>{{ __('ui.purchasing.po_statuses.'.$s) }}</option>
                        @endforeach
                    </select>
                    <x-secondary-button type="submit">{{ __('ui.search') }}</x-secondary-button>
                </form>
                <x-lentera.dropdown-aksi>
                    @can('po.create')
                        <x-lentera.menu-item :href="route('po.create')">{{ __('ui.purchasing.po_create') }}</x-lentera.menu-item>
                    @endcan
                </x-lentera.dropdown-aksi>
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.purchasing.number') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.purchasing.supplier') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.purchasing.date') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.purchasing.destination_branch') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.purchasing.total') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.purchasing.status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($orders as $po)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 font-medium"><a href="{{ route('po.show', $po) }}" class="text-lentera-800 underline-offset-2 hover:underline">{{ $po->number }}</a></td>
                                <td class="px-4 py-3">{{ $po->supplier->name }}</td>
                                <td class="px-4 py-3">{{ $po->order_date->format('d/m/Y') }}</td>
                                <td class="px-4 py-3">{{ $po->branch->name }}</td>
                                <td class="px-4 py-3 tnum">{{ \App\Support\Money::fromDecimal((string) $po->total_amount)->format() }}</td>
                                <td class="px-4 py-3"><x-lentera.badge>{{ __('ui.purchasing.po_statuses.'.$po->status) }}</x-lentera.badge></td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><x-lentera.empty-state>{{ __('ui.purchasing.empty_po') }}</x-lentera.empty-state></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $orders->links() }}</div>
        </div>
    </div>
</x-app-layout>
