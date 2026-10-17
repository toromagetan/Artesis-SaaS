<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.purchasing.receipt_title') }} — {{ $po->number }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-4xl sm:px-6 lg:px-8">
            @if ($errors->any())
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif
            <div class="rounded-lg bg-white p-6 shadow-soft">
                <p class="mb-4 text-sm text-gray-600">{{ $po->supplier->name }} → {{ $po->branch->name }}</p>
                <form method="POST" action="{{ route('po.receive.store', $po) }}">
                    @csrf
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="received_date" :value="__('ui.purchasing.received_date')" />
                            <x-text-input id="received_date" name="received_date" type="date" class="mt-1 block w-full" :value="now()->toDateString()" />
                        </div>
                        <div>
                            <x-input-label for="delivery_note_no" :value="__('ui.purchasing.delivery_note')" />
                            <x-text-input id="delivery_note_no" name="delivery_note_no" class="mt-1 block w-full" :value="old('delivery_note_no')" maxlength="80" />
                        </div>
                    </div>

                    <div class="mt-6 space-y-2">
                        @foreach ($po->items as $i => $item)
                            <div class="grid grid-cols-12 items-end gap-2">
                                <div class="col-span-5 text-sm">
                                    <span class="font-medium text-gray-800">{{ $item->product->name }}</span>
                                    <span class="block text-xs text-gray-400">{{ __('ui.purchasing.qty_ordered') }}: {{ $item->qty_ordered }} · {{ __('ui.purchasing.qty_received') }}: {{ $item->qty_received }} · {{ __('ui.purchasing.qty_remaining') }}: {{ $item->remainingQty() }}</span>
                                </div>
                                <input type="hidden" name="items[{{ $i }}][purchase_order_item_id]" value="{{ $item->id }}">
                                <div class="col-span-3">
                                    <x-input-label :value="__('ui.purchasing.qty_receive_now')" />
                                    <x-text-input name="items[{{ $i }}][qty]" type="number" min="0" class="mt-1 block w-full tnum" :value="old('items.'.$i.'.qty')" />
                                </div>
                                <div class="col-span-4">
                                    <x-input-label :value="__('ui.purchasing.unit_cost_actual')" />
                                    <x-text-input name="items[{{ $i }}][unit_cost_actual]" type="number" step="0.01" min="0" class="mt-1 block w-full tnum" :value="old('items.'.$i.'.unit_cost_actual', (string) $item->unit_cost)" />
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-4">
                        <x-input-label for="over_reason" :value="__('ui.purchasing.over_reason')" />
                        <x-text-input id="over_reason" name="over_reason" class="mt-1 block w-full" :value="old('over_reason')" maxlength="255" />
                        <p class="mt-1 text-xs text-gray-400">{{ __('ui.purchasing.over_receive_forbidden') }}</p>
                    </div>

                    <div class="mt-6 flex items-center gap-3">
                        <a href="{{ route('po.show', $po) }}" class="text-sm text-gray-600 underline">{{ __('ui.back') }}</a>
                        <x-primary-button>{{ __('ui.save') }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
