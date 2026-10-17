<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.purchasing.return_title') }} — {{ $receipt->number }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-4xl sm:px-6 lg:px-8">
            @if ($errors->any())
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif
            <div class="rounded-lg bg-white p-6 shadow-soft">
                <form method="POST" action="{{ route('receipts.return.store', $receipt) }}">
                    @csrf
                    <div class="space-y-2">
                        @foreach ($receipt->items as $i => $item)
                            <div class="grid grid-cols-12 items-end gap-2">
                                <div class="col-span-8 text-sm">
                                    <span class="font-medium text-gray-800">{{ $item->purchaseOrderItem->product->name }}</span>
                                    <span class="block text-xs text-gray-400">{{ __('ui.purchasing.qty') }}: {{ $item->qty }} · sudah diretur: {{ $item->returnedQty() }}</span>
                                </div>
                                <input type="hidden" name="items[{{ $i }}][receipt_item_id]" value="{{ $item->id }}">
                                <div class="col-span-4">
                                    <x-input-label :value="__('ui.purchasing.return_qty')" />
                                    <x-text-input name="items[{{ $i }}][qty]" type="number" min="0" max="{{ $item->qty - $item->returnedQty() }}" class="mt-1 block w-full tnum" :value="old('items.'.$i.'.qty')" />
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-4">
                        <x-input-label for="reason" :value="__('ui.purchasing.return_reason')" />
                        <x-text-input id="reason" name="reason" class="mt-1 block w-full" :value="old('reason')" required maxlength="255" />
                    </div>
                    <div class="mt-6 flex items-center gap-3">
                        <a href="{{ route('receipts.show', $receipt) }}" class="text-sm text-gray-600 underline">{{ __('ui.back') }}</a>
                        <x-primary-button>{{ __('ui.save') }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
