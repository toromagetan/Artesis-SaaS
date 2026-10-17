<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.products.detail') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-lg border border-lentera-200 bg-lentera-50 px-4 py-3 text-sm text-lentera-800">{{ session('status') }}</div>
            @endif

            <div class="mb-4 flex items-center justify-between">
                <a href="{{ route('products.index', $product->type) }}" class="text-sm text-gray-600 underline">{{ __('ui.back') }}</a>
                @if (auth()->user()->hasPermission('products.update') || auth()->user()->hasPermission('products.deactivate') || auth()->user()->hasPermission('products.create'))
                    <x-lentera.dropdown-aksi>
                        @can('products.create')
                            <x-lentera.menu-item :href="route('products.create', $product->type)">{{ __('ui.products.create') }}</x-lentera.menu-item>
                        @endcan
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
                    </x-lentera.dropdown-aksi>
                @endif
            </div>

            <div class="rounded-lg bg-white p-6 shadow-soft">
                <div class="mb-4 flex items-center gap-3">
                    <h2 class="text-lg font-semibold text-gray-800">{{ $product->name }}</h2>
                    <span class="inline-flex items-center rounded-full border border-gold/40 bg-gold/10 px-2 py-0.5 text-xs font-semibold text-gold-dark">{{ $product->typeLabel() }}</span>
                    <x-lentera.badge :active="$product->is_active" />
                </div>
                <dl class="grid grid-cols-[160px_1fr] gap-y-3 text-sm">
                    <dt class="text-gray-500">{{ __('ui.products.sku') }}</dt>
                    <dd class="font-medium text-gray-800">{{ $product->sku }}</dd>
                    <dt class="text-gray-500">{{ __('ui.products.brand') }}</dt>
                    <dd class="font-medium text-gray-800">{{ $product->brand ?: '—' }}</dd>
                    <dt class="text-gray-500">{{ __('ui.products.color') }}</dt>
                    <dd class="font-medium text-gray-800">{{ $product->color ?: '—' }}</dd>
                    <dt class="text-gray-500">{{ __('ui.products.cost_price') }}</dt>
                    <dd class="font-medium text-gray-800 tnum">{{ \App\Support\Money::fromDecimal((string) $product->cost_price)->format() }}</dd>
                    <dt class="text-gray-500">{{ __('ui.products.sale_price') }}</dt>
                    <dd class="font-medium text-gray-800 tnum">{{ \App\Support\Money::fromDecimal((string) $product->sale_price)->format() }}</dd>
                    @foreach (\App\Modules\Catalog\Services\ProductService::ATTRIBUTE_KEYS[$product->type] as $key)
                        <dt class="text-gray-500">{{ __('ui.products.attributes.'.$key) }}</dt>
                        <dd class="font-medium text-gray-800">
                            @if ($key === 'expiry_tracked')
                                {{ $product->attributeValue($key) === '1' ? __('ui.yes') : __('ui.no') }}
                            @else
                                {{ $product->attributeValue($key) ?: '—' }}
                            @endif
                        </dd>
                    @endforeach
                </dl>
            </div>
        </div>
    </div>
</x-app-layout>
