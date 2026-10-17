<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.transfer.title') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-lg border border-lentera-200 bg-lentera-50 px-4 py-3 text-sm text-lentera-800">{{ session('status') }}</div>
            @endif
            <div class="mb-4 flex items-center justify-between">
                <form method="GET" class="flex gap-2">
                    <select name="status" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                        <option value="">{{ __('ui.transfer.status') }}</option>
                        @foreach (\App\Modules\Inventory\Models\StockTransfer::STATUSES as $s)
                            <option value="{{ $s }}" @selected($status === $s)>{{ __('ui.transfer.statuses.'.$s) }}</option>
                        @endforeach
                    </select>
                    <x-secondary-button type="submit">{{ __('ui.search') }}</x-secondary-button>
                </form>
                <x-lentera.dropdown-aksi>
                    @can('stock.transfer')
                        <x-lentera.menu-item :href="route('transfers.create')">{{ __('ui.transfer.create') }}</x-lentera.menu-item>
                    @endcan
                </x-lentera.dropdown-aksi>
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.transfer.number') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.transfer.from') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.transfer.to') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.transfer.source') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.transfer.items') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.transfer.status') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.transfer.sender') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($transfers as $transfer)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 font-medium"><a href="{{ route('transfers.show', $transfer) }}" class="text-lentera-800 underline-offset-2 hover:underline">{{ $transfer->number }}</a></td>
                                <td class="px-4 py-3">{{ $transfer->fromBranch->name }}</td>
                                <td class="px-4 py-3">{{ $transfer->toBranch->name }}</td>
                                <td class="px-4 py-3">{{ $transfer->source_type === 'purchase_order' ? __('ui.transfer.source_po').' '.$transfer->purchaseOrder?->number : __('ui.transfer.source_stock') }}</td>
                                <td class="px-4 py-3 tnum">{{ $transfer->items_count }}</td>
                                <td class="px-4 py-3"><x-lentera.badge>{{ __('ui.transfer.statuses.'.$transfer->status) }}</x-lentera.badge></td>
                                <td class="px-4 py-3">{{ $transfer->sender->name }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><x-lentera.empty-state>{{ __('ui.transfer.empty') }}</x-lentera.empty-state></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $transfers->links() }}</div>
        </div>
    </div>
</x-app-layout>
