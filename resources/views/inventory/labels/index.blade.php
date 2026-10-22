<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.labels.title') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            @if ($errors->any())
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
            @endif

            <form method="GET" class="mb-4 flex gap-2">
                <x-text-input name="q" :value="$q" :placeholder="__('ui.labels.search_placeholder')" class="w-72" />
                <x-secondary-button type="submit">{{ __('ui.search') }}</x-secondary-button>
            </form>

            <form id="label-form" method="POST" action="{{ route('labels.print') }}" target="_blank" x-data="{ mode: 'products' }">
                @csrf
                <div class="mb-4 flex items-center justify-between">
                    <div class="flex items-center gap-4 text-sm">
                        <label class="inline-flex items-center gap-2">
                            <input type="radio" name="mode" value="products" x-model="mode" class="border-gray-300 text-lentera-600 focus:ring-lentera-500">
                            {{ __('ui.labels.mode_products') }}
                        </label>
                        <label class="inline-flex items-center gap-2">
                            <input type="radio" name="mode" value="receipt" x-model="mode" class="border-gray-300 text-lentera-600 focus:ring-lentera-500">
                            {{ __('ui.labels.mode_receipt') }}
                        </label>
                    </div>
                    <x-lentera.aksi-bar>
                        <button type="submit" form="label-form" class="block w-full px-4 py-2 text-left text-sm text-gray-700 transition duration-150 hover:bg-lentera-50">{{ __('ui.labels.print') }}</button>
                    </x-lentera.aksi-bar>
                </div>

                <div x-show="mode === 'receipt'" class="mb-4 rounded-lg bg-white p-4 shadow-soft" style="display: none;">
                    <div class="flex flex-wrap items-end gap-4">
                        <div class="min-w-64">
                            <x-input-label for="receipt_id" :value="__('ui.labels.receipt')" />
                            <select id="receipt_id" name="receipt_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                                @foreach ($receipts as $receipt)
                                    <option value="{{ $receipt->id }}">{{ $receipt->number }} · {{ $receipt->purchaseOrder?->number }} · {{ $receipt->received_date }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="multiplier" :value="__('ui.labels.multiplier')" />
                            <x-text-input id="multiplier" name="multiplier" type="number" min="1" max="50" value="1" class="mt-1 block w-24 tnum" />
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-gray-400">{{ __('ui.labels.receipt_hint') }}</p>
                </div>

                <div x-show="mode === 'products'" class="overflow-hidden rounded-lg bg-white shadow-soft">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 text-left text-gray-500">
                                <th class="px-4 py-3 font-medium">{{ __('ui.labels.select') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('ui.products.name') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('ui.products.sku') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('ui.products.barcode') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('ui.products.sale_price') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('ui.labels.copies') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($products as $product)
                                <tr class="border-b border-gray-50 last:border-0">
                                    <td class="px-4 py-3"><input type="checkbox" name="selected[]" value="{{ $product->id }}" class="rounded border-gray-300 text-lentera-600 focus:ring-lentera-500"></td>
                                    <td class="px-4 py-3 font-medium text-gray-800">{{ $product->name }}</td>
                                    <td class="px-4 py-3">{{ $product->sku }}</td>
                                    <td class="px-4 py-3">{{ $product->barcode ?: '—' }}</td>
                                    <td class="px-4 py-3 tnum">{{ \App\Support\Money::fromDecimal((string) $product->sale_price)->format() }}</td>
                                    <td class="px-4 py-3"><input type="number" name="copies[{{ $product->id }}]" value="1" min="0" max="500" class="w-20 rounded-md border-gray-300 text-sm tnum shadow-sm focus:border-lentera-500 focus:ring-lentera-500"></td>
                                </tr>
                            @empty
                                <tr><td colspan="6"><x-lentera.empty-state>{{ __('ui.products.empty') }}</x-lentera.empty-state></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-4" x-show="mode === 'products'">{{ $products->links() }}</div>
            </form>
        </div>
    </div>
</x-app-layout>
