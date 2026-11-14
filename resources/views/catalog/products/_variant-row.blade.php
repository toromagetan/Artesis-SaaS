<tr class="border-b border-gray-50 last:border-0">
    <td class="px-4 py-3 font-medium text-gray-800">
        <span class="flex items-center gap-3">
            @if ($product->imageUrl())
                <img src="{{ $product->imageUrl() }}" alt="" class="h-9 w-9 rounded object-cover">
            @endif
            <a href="{{ route('products.show', [$product::DETAIL_TYPE, $product->id]) }}" class="text-lentera-800 underline-offset-2 hover:underline">{{ $product->product->name }}</a>
            @if ($product::DETAIL_TYPE === 'accessory' && $product->is_process_item)
                <span class="inline-flex items-center rounded-full border border-lentera-200 bg-lentera-50 px-2 py-0.5 text-xs font-semibold text-lentera-700">{{ __('ui.products.process_badge') }}</span>
            @endif
        </span>
    </td>
    <td class="px-4 py-3">{{ $product->sku }}</td>
    <td class="px-4 py-3 text-gray-600">{{ $product->detailLabel() ?: '—' }}</td>
    <td class="px-4 py-3 tnum">{{ \App\Support\Money::fromDecimal((string) $product->sale_price)->format() }}</td>
    @if ($type === 'softlens')
        <td class="px-4 py-3">
            @isset($expiries[$product->id])
                <span class="tnum {{ $expiries[$product->id]['expired'] ? 'font-semibold text-red-600' : ($expiries[$product->id]['soon'] ? 'font-medium text-gold-dark' : '') }}">{{ \Illuminate\Support\Carbon::parse($expiries[$product->id]['date'])->format('d/m/Y') }}</span>
            @else
                <span class="text-gray-400">—</span>
            @endisset
        </td>
    @endif
    <td class="px-4 py-3"><x-lentera.badge :active="$product->is_active" /></td>
    <td class="px-4 py-3 text-right">
        <x-lentera.aksi-baris>
            <x-lentera.menu-item :href="route('products.show', [$product::DETAIL_TYPE, $product->id])" sm icon="eye" :title="__('ui.products.detail')"><span class="sr-only">{{ __('ui.products.detail') }}</span></x-lentera.menu-item>
            @can('products.update')
                @php($typeLocked = app(\App\Modules\Catalog\Services\ProductService::class)->typeIsLocked($product))
                <x-lentera.aksi-dialog :label="__('ui.edit')" :title="__('ui.products.edit').' — '.$product->product->name" sm wide icon="pencil">
                    <form method="POST" action="{{ route('products.update', [$product::DETAIL_TYPE, $product->id]) }}" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')
                        <div class="mb-4">
                            <x-input-label :for="'type-'.$product->id" :value="__('ui.products.type')" />
                            <select id="type-{{ $product->id }}" name="type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500" @disabled($typeLocked)>
                                @foreach (\App\Modules\Catalog\Models\Product::TYPES as $t)
                                    <option value="{{ $t }}" @selected(old('type', $product::DETAIL_TYPE) === $t)>{{ __('ui.products.types.'.$t) }}</option>
                                @endforeach
                            </select>
                            @if ($typeLocked)
                                <input type="hidden" name="type" value="{{ $product::DETAIL_TYPE }}">
                                <p class="mt-1 text-xs text-gray-400">{{ __('ui.products.type_locked') }}</p>
                            @endif
                        </div>
                        @include('catalog.products._form', ['type' => $product::DETAIL_TYPE, 'parent' => $product->product, 'parents' => $masterOptions, 'idPrefix' => 'edit-'.$product->id.'-'])
                        <div class="mt-6 flex items-center gap-3">
                            <x-primary-button>{{ __('ui.save_changes') }}</x-primary-button>
                        </div>
                    </form>
                </x-lentera.aksi-dialog>
            @endcan
            @can('products.deactivate')

                <form method="POST" action="{{ route('products.toggle-active', [$product::DETAIL_TYPE, $product->id]) }}">
                    @csrf
                    <button type="submit" title="{{ $product->is_active ? __('ui.deactivate') : __('ui.activate') }}" aria-label="{{ $product->is_active ? __('ui.deactivate') : __('ui.activate') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-[13px] font-medium shadow-soft transition duration-150 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-lentera-500 {{ $product->is_active ? 'text-red-600' : 'text-lentera-700' }}"><svg class="h-4 w-4 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v8" /><path stroke-linecap="round" stroke-linejoin="round" d="M6.34 6.34a8 8 0 1011.32 0" /></svg><span class="sr-only">{{ $product->is_active ? __('ui.deactivate') : __('ui.activate') }}</span></button>
                </form>
            @endcan
        </x-lentera.aksi-baris>
    </td>
</tr>
