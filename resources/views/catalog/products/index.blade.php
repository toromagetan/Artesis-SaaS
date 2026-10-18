<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.products.types.'.$type) }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-lg border border-lentera-200 bg-lentera-50 px-4 py-3 text-sm text-lentera-800">{{ session('status') }}</div>
            @endif

            <div class="mb-4 flex items-center justify-between">
                <form method="GET" class="flex gap-2">
                    <x-text-input name="q" :value="$q" :placeholder="__('ui.products.search_placeholder')" class="w-72" />
                    <x-secondary-button type="submit">{{ __('ui.search') }}</x-secondary-button>
                </form>
                @can('products.create')
                    <x-lentera.dropdown-aksi>
                        <x-lentera.menu-item :href="route('products.create', $type)">{{ __('ui.products.create') }}</x-lentera.menu-item>
                    </x-lentera.dropdown-aksi>
                @endcan
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.products.name') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.products.sku') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.products.brand') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.products.sale_price') }}</th>
                            @if ($type === 'softlens')
                                <th class="px-4 py-3 font-medium">{{ __('ui.products.nearest_expiry') }}</th>
                            @endif
                            <th class="px-4 py-3 font-medium">{{ __('ui.products.status') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($products as $product)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 font-medium text-gray-800">
                                    <a href="{{ route('products.show', $product) }}" class="text-lentera-800 underline-offset-2 hover:underline">{{ $product->name }}</a>
                                </td>
                                <td class="px-4 py-3">{{ $product->sku }}</td>
                                <td class="px-4 py-3">{{ $product->brand ?: '—' }}</td>
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
                        @empty
                            <tr><td colspan="{{ $type === 'softlens' ? 7 : 6 }}"><x-lentera.empty-state>{{ __('ui.products.empty') }}</x-lentera.empty-state></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $products->links() }}</div>
        </div>
    </div>
</x-app-layout>
