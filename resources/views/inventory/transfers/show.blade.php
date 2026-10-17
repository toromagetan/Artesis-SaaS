<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ $transfer->number }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-lg border border-lentera-200 bg-lentera-50 px-4 py-3 text-sm text-lentera-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            <div class="mb-4 flex items-center justify-between">
                <a href="{{ route('transfers.index') }}" class="text-sm text-gray-600 underline">{{ __('ui.back') }}</a>
                <div class="flex items-center gap-2">
                    <x-lentera.dropdown-cetak>
                        <x-lentera.menu-item :href="route('transfers.print', $transfer)" target="_blank">{{ __('ui.transfer.print') }}</x-lentera.menu-item>
                    </x-lentera.dropdown-cetak>
                    @if ($transfer->status === 'sent')
                        <x-lentera.dropdown-aksi>
                            @can('stock.receive_transfer')
                                <form method="POST" action="{{ route('transfers.receive', $transfer) }}">
                                    @csrf
                                    <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-gray-700 transition duration-150 hover:bg-lentera-50 hover:text-lentera-900">{{ __('ui.transfer.receive') }}</button>
                                </form>
                            @endcan
                            @can('stock.transfer')
                                <div class="my-1 border-t border-gray-100"></div>
                                <form method="POST" action="{{ route('transfers.cancel', $transfer) }}" onsubmit="return confirm('{{ __('ui.transfer.cancel') }}?')">
                                    @csrf
                                    <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-red-600 transition duration-150 hover:bg-red-50">{{ __('ui.transfer.cancel') }}</button>
                                </form>
                            @endcan
                        </x-lentera.dropdown-aksi>
                    @endif
                </div>
            </div>

            <div class="mb-6 rounded-lg bg-white p-6 shadow-soft">
                <dl class="grid grid-cols-[160px_1fr] gap-y-3 text-sm">
                    <dt class="text-gray-500">{{ __('ui.transfer.from') }}</dt>
                    <dd class="font-medium text-gray-800">{{ $transfer->fromBranch->name }}</dd>
                    <dt class="text-gray-500">{{ __('ui.transfer.to') }}</dt>
                    <dd class="font-medium text-gray-800">{{ $transfer->toBranch->name }}</dd>
                    <dt class="text-gray-500">{{ __('ui.transfer.source') }}</dt>
                    <dd class="font-medium text-gray-800">
                        @if ($transfer->source_type === 'purchase_order' && $transfer->purchaseOrder)
                            {{ __('ui.transfer.source_po') }} — <a class="text-lentera-800 underline-offset-2 hover:underline" href="{{ route('po.show', $transfer->purchaseOrder) }}">{{ $transfer->purchaseOrder->number }}</a>
                        @else
                            {{ __('ui.transfer.source_stock') }}
                        @endif
                    </dd>
                    <dt class="text-gray-500">{{ __('ui.transfer.status') }}</dt>
                    <dd class="font-medium text-gray-800">{{ __('ui.transfer.statuses.'.$transfer->status) }}</dd>
                    <dt class="text-gray-500">{{ __('ui.transfer.sender') }}</dt>
                    <dd class="font-medium text-gray-800">{{ $transfer->sender->name }} · {{ $transfer->created_at->format('d/m/Y H:i') }}</dd>
                    @if ($transfer->receiver)
                        <dt class="text-gray-500">{{ __('ui.transfer.receiver') }}</dt>
                        <dd class="font-medium text-gray-800">{{ $transfer->receiver->name }} · {{ $transfer->received_at?->format('d/m/Y H:i') }}</dd>
                    @endif
                    @if ($transfer->notes)
                        <dt class="text-gray-500">{{ __('ui.purchasing.notes') }}</dt>
                        <dd class="font-medium text-gray-800">{{ $transfer->notes }}</dd>
                    @endif
                </dl>
            </div>

            <h2 class="mb-2 text-base font-semibold text-gray-800">{{ __('ui.transfer.items') }}</h2>
            <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.purchasing.product') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.purchasing.qty') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($transfer->items as $item)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 font-medium text-gray-800">{{ $item->product->name }}</td>
                                <td class="px-4 py-3 tnum">{{ $item->qty }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
