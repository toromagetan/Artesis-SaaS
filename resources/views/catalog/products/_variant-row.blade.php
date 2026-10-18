<tr class="border-b border-gray-50 last:border-0">
    <td class="px-4 py-3 font-medium text-gray-800">
        <span class="flex items-center gap-3">
            @if ($product->imageUrl())
                <img src="{{ $product->imageUrl() }}" alt="" class="h-9 w-9 rounded object-cover">
            @endif
            <a href="{{ route('products.show', $product) }}" class="text-lentera-800 underline-offset-2 hover:underline">{{ $product->name }}</a>
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
        <x-lentera.row-actions>
            <x-lentera.menu-item :href="route('products.show', $product)">{{ __('ui.products.detail') }}</x-lentera.menu-item>
            @can('products.update')
                <x-lentera.menu-item :href="route('products.edit', $product)">{{ __('ui.edit') }}</x-lentera.menu-item>
            @endcan
            @can('products.deactivate')
                <div class="my-1 border-t border-gray-100"></div>
                <form method="POST" action="{{ route('products.toggle-active', $product) }}">
                    @csrf
                    <button type="submit" class="block w-full px-4 py-2 text-left text-sm transition duration-150 hover:bg-lentera-50 {{ $product->is_active ? 'text-red-600' : 'text-lentera-700' }}">{{ $product->is_active ? __('ui.deactivate') : __('ui.activate') }}</button>
                </form>
            @endcan
        </x-lentera.row-actions>
    </td>
</tr>
