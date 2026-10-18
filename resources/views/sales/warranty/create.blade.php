<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.warranty.create') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl space-y-6 sm:px-6 lg:px-8">
            <div class="rounded-lg bg-white p-6 shadow-soft">
                <form method="GET" action="{{ route('warranties.create') }}" class="flex gap-3">
                    <x-text-input name="invoice" class="block w-full" :value="$invoice" placeholder="{{ __('ui.warranty.invoice') }}" required />
                    <x-primary-button>{{ __('ui.warranty.find_invoice') }}</x-primary-button>
                </form>
                <p class="mt-2 text-xs text-gray-400">{{ __('ui.warranty.invoice_hint') }}</p>
                @if ($invoice !== '' && $sale === null)
                    <p class="mt-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ __('ui.warranty.not_found') }}</p>
                @endif
            </div>

            @if ($sale)
                <div class="rounded-lg bg-white p-6 shadow-soft">
                    <p class="text-sm text-gray-500">{{ $sale->number }} — {{ $sale->customer->name ?? '' }}</p>
                    <form method="POST" action="{{ route('warranties.store') }}" class="mt-4 space-y-4">
                        @csrf
                        <input type="hidden" name="sale_id" value="{{ $sale->id }}">
                        <div>
                            <x-input-label for="sale_item_id" :value="__('ui.warranty.item')" />
                            <select id="sale_item_id" name="sale_item_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500" required>
                                @foreach ($sale->items as $item)
                                    <option value="{{ $item->id }}">{{ $item->product_name_snapshot }} — qty {{ $item->qty }} × {{ \App\Support\Money::fromDecimal((string) $item->unit_price)->format() }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('sale_item_id')" class="mt-2" />
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <x-input-label for="quantity" :value="__('ui.warranty.qty')" />
                                <x-text-input id="quantity" name="quantity" type="number" min="1" value="1" class="mt-1 block w-full" required />
                                <x-input-error :messages="$errors->get('quantity')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="reason" :value="__('ui.warranty.reason')" />
                                <x-text-input id="reason" name="reason" class="mt-1 block w-full" required maxlength="500" />
                                <x-input-error :messages="$errors->get('reason')" class="mt-2" />
                            </div>
                        </div>
                        <div>
                            <x-input-label for="description" :value="__('ui.warranty.description')" />
                            <x-text-input id="description" name="description" class="mt-1 block w-full" maxlength="2000" />
                        </div>
                        <div class="flex justify-end">
                            <x-primary-button>{{ __('ui.warranty.create') }}</x-primary-button>
                        </div>
                    </form>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
