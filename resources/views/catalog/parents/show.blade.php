<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ $parent->name }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-lg border border-lentera-200 bg-lentera-50 px-4 py-3 text-sm text-lentera-800">{{ session('status') }}</div>
            @endif

            <div class="mb-4 flex items-center justify-between">
                <a href="{{ route('products.index', $parent->type) }}" class="text-sm text-gray-600 underline">{{ __('ui.back') }}</a>
                <x-lentera.dropdown-aksi>
                    @can('products.create')
                        <x-lentera.menu-item :href="route('products.create', ['type' => $parent->type, 'induk' => $parent->id])">{{ __('ui.products.add_variant') }}</x-lentera.menu-item>
                    @endcan
                    @can('products.update')
                        <x-lentera.menu-item :href="route('products.parents.edit', $parent)">{{ __('ui.edit') }}</x-lentera.menu-item>
                    @endcan
                    @can('products.deactivate')
                        <div class="my-1 border-t border-gray-100"></div>
                        <form method="POST" action="{{ route('products.parents.toggle-active', $parent) }}">
                            @csrf
                            <button type="submit" class="block w-full px-4 py-2 text-left text-sm transition duration-150 hover:bg-lentera-50 {{ $parent->is_active ? 'text-red-600' : 'text-lentera-700' }}">{{ $parent->is_active ? __('ui.deactivate') : __('ui.activate') }}</button>
                        </form>
                    @endcan
                </x-lentera.dropdown-aksi>
            </div>

            <div class="mb-4 rounded-lg bg-white p-6 shadow-soft">
                <dl class="grid grid-cols-2 gap-4 text-sm md:grid-cols-4">
                    <div><dt class="text-gray-500">{{ __('ui.products.type') }}</dt><dd class="mt-1 font-medium text-gray-800">{{ $parent->typeLabel() }}</dd></div>
                    <div><dt class="text-gray-500">{{ __('ui.products.brand') }}</dt><dd class="mt-1 font-medium text-gray-800">{{ $parent->brand ?: '—' }}</dd></div>
                    <div><dt class="text-gray-500">{{ __('ui.products.status') }}</dt><dd class="mt-1"><x-lentera.badge :active="$parent->is_active" /></dd></div>
                    <div><dt class="text-gray-500">{{ __('ui.products.variants') }}</dt><dd class="mt-1 font-medium text-gray-800">{{ $parent->variants->count() }}</dd></div>
                </dl>
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.products.sku') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.products.detail') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.products.sale_price') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.products.stock_total') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.products.status') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($parent->variants as $variant)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3">
                                    <span class="flex items-center gap-3">
                                        @if ($variant->imageUrl())<img src="{{ $variant->imageUrl() }}" alt="" class="h-9 w-9 rounded object-cover">@endif
                                        <a href="{{ route('products.show', $variant) }}" class="font-medium text-lentera-800 underline-offset-2 hover:underline">{{ $variant->sku }}</a>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ $variant->detailLabel() ?: '—' }}</td>
                                <td class="px-4 py-3 tnum">{{ \App\Support\Money::fromDecimal((string) $variant->sale_price)->format() }}</td>
                                <td class="px-4 py-3 tnum">{{ $stockByVariant[$variant->id] ?? 0 }}</td>
                                <td class="px-4 py-3"><x-lentera.badge :active="$variant->is_active" /></td>
                                <td class="px-4 py-3 text-right">
                                    <x-lentera.row-actions>
                                        <x-lentera.menu-item :href="route('products.show', $variant)">{{ __('ui.products.detail') }}</x-lentera.menu-item>
                                        @can('products.update')
                                            <x-lentera.menu-item :href="route('products.edit', $variant)">{{ __('ui.edit') }}</x-lentera.menu-item>
                                        @endcan
                                    </x-lentera.row-actions>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><x-lentera.empty-state>{{ __('ui.products.no_variants') }}</x-lentera.empty-state></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
