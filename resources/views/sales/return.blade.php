<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.sales.make_return') }} — {{ $sale->number }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-4xl sm:px-6 lg:px-8">
            @if ($errors->any())
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif
            <div class="rounded-lg bg-white p-6 shadow-soft">
                <form method="POST" action="{{ route('sales.return.store', $sale) }}">
                    @csrf
                    <div class="space-y-2">
                        @foreach ($sale->items as $i => $item)
                            <div class="grid grid-cols-12 items-end gap-2">
                                <div class="col-span-5 text-sm">
                                    <span class="font-medium text-gray-800">{{ $item->product_name_snapshot }}</span>
                                    <span class="block text-xs text-gray-400">{{ __('ui.sales.qty') }}: {{ $item->qty }} · {{ __('ui.sales.returned_qty') }}: {{ $item->returnedQty() }}</span>
                                </div>
                                <input type="hidden" name="items[{{ $i }}][sale_item_id]" value="{{ $item->id }}">
                                <div class="col-span-2">
                                    <x-text-input name="items[{{ $i }}][qty]" type="number" min="0" max="{{ $item->qty - $item->returnedQty() }}" class="block w-full tnum" :value="old('items.'.$i.'.qty')" placeholder="Qty retur" />
                                </div>
                                <div class="col-span-5">
                                    <select name="items[{{ $i }}][condition]" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                                        @foreach (['resale', 'damaged'] as $condition)
                                            <option value="{{ $condition }}" @selected(old('items.'.$i.'.condition') === $condition)>{{ __('ui.sales.conditions.'.$condition) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-4 grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="reason" :value="__('ui.sales.return_reason')" />
                            <x-text-input id="reason" name="reason" class="mt-1 block w-full" :value="old('reason')" required maxlength="255" />
                        </div>
                        <div>
                            <x-input-label for="refund_method" :value="__('ui.sales.refund_method')" />
                            <select id="refund_method" name="refund_method" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                                @foreach (['cash', 'transfer', 'store_credit'] as $method)
                                    <option value="{{ $method }}" @selected(old('refund_method') === $method)>{{ __('ui.sales.refund_methods.'.$method) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="mt-6 flex items-center gap-3">
                        <a href="{{ route('sales.show', $sale) }}" class="text-sm text-gray-600 underline">{{ __('ui.back') }}</a>
                        <x-primary-button>{{ __('ui.save') }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
