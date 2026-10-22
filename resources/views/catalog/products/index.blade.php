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
                    <x-lentera.aksi-bar>
                        <x-lentera.menu-item :href="route('products.parents.create', $type)" primary>{{ __('ui.products.parent_create') }}</x-lentera.menu-item>
                        <x-lentera.menu-item :href="route('products.create', $type)">{{ __('ui.products.create') }}</x-lentera.menu-item>
                    </x-lentera.aksi-bar>
                @endcan
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.products.name') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.products.sku') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.products.detail') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.products.sale_price') }}</th>
                            @if ($type === 'softlens')
                                <th class="px-4 py-3 font-medium">{{ __('ui.products.nearest_expiry') }}</th>
                            @endif
                            <th class="px-4 py-3 font-medium">{{ __('ui.products.status') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($parents as $parent)
                            <tr class="border-b border-gray-100 bg-lentera-50/60">
                                <td colspan="{{ $type === 'softlens' ? 7 : 6 }}" class="px-4 py-2.5">
                                    <a href="{{ route('products.parents.show', $parent) }}" class="font-semibold text-lentera-900 underline-offset-2 hover:underline">{{ $parent->name }}</a>
                                    @if ($parent->brand)<span class="text-gray-500">· {{ $parent->brand }}</span>@endif
                                    <span class="text-gray-500">· {{ __('ui.products.variant_count', ['count' => $parent->variants->count()]) }}</span>
                                    @unless ($parent->is_active)<span class="ml-2 text-xs font-medium text-red-600">{{ __('ui.inactive') }}</span>@endunless
                                </td>
                            </tr>
                            @foreach ($parent->variants as $product)
                                @include('catalog.products._variant-row', ['product' => $product])
                            @endforeach
                        @empty
                            @if ($orphans->isEmpty())
                                <tr><td colspan="{{ $type === 'softlens' ? 7 : 6 }}"><x-lentera.empty-state>{{ __('ui.products.empty') }}</x-lentera.empty-state></td></tr>
                            @endif
                        @endforelse
                        @if ($orphans->isNotEmpty())
                            <tr class="border-b border-gray-100 bg-gray-50">
                                <td colspan="{{ $type === 'softlens' ? 7 : 6 }}" class="px-4 py-2.5 font-semibold text-gray-600">{{ __('ui.products.no_parent') }}</td>
                            </tr>
                            @foreach ($orphans as $product)
                                @include('catalog.products._variant-row', ['product' => $product])
                            @endforeach
                        @endif
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $parents->links() }}</div>
        </div>
    </div>
</x-app-layout>
