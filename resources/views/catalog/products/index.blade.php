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
                    <span class="flex items-center gap-2">
                        <a href="{{ route('products.import.form') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-sm font-medium text-gray-700 shadow-soft transition duration-150 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-lentera-500">{{ __('ui.product_import.title') }}</a>
                    <x-lentera.aksi-dialog :label="__('ui.products.create')" :title="__('ui.products.create').' — '.__('ui.products.types.'.$type)" wide>
                        <form method="POST" action="{{ route('products.store', $type) }}" enctype="multipart/form-data">
                            @csrf
                            @include('catalog.products._form', ['product' => null, 'parent' => null, 'parents' => $masterOptions, 'idPrefix' => 'create-'])
                            <div class="mt-6 flex items-center gap-3">
                                <x-primary-button>{{ __('ui.save') }}</x-primary-button>
                            </div>
                        </form>
                    </x-lentera.aksi-dialog>
                    </span>
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
                                    <span class="flex items-center justify-between gap-3">
                                        <span>
                                            <span class="font-semibold text-lentera-900">{{ $parent->name }}</span>
                                            @if ($parent->brand)<span class="text-gray-500">· {{ $parent->brand }}</span>@endif
                                            <span class="text-gray-500">· {{ __('ui.products.variant_count', ['count' => $parent->variants->count()]) }}</span>
                                            @unless ($parent->is_active)<span class="ml-2 text-xs font-medium text-red-600">{{ __('ui.inactive') }}</span>@endunless
                                        </span>
                                        @can('products.deactivate')
                                            <form method="POST" action="{{ route('products.masters.toggle-active', $parent) }}">
                                                @csrf
                                                <button type="submit" title="{{ $parent->is_active ? __('ui.deactivate') : __('ui.activate') }}" aria-label="{{ $parent->is_active ? __('ui.deactivate') : __('ui.activate') }}" class="inline-flex items-center rounded-md p-1.5 transition duration-150 hover:bg-white focus:outline-none focus:ring-2 focus:ring-lentera-500 {{ $parent->is_active ? 'text-red-600' : 'text-lentera-700' }}"><svg class="h-4 w-4 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v8" /><path stroke-linecap="round" stroke-linejoin="round" d="M6.34 6.34a8 8 0 1011.32 0" /></svg><span class="sr-only">{{ $parent->is_active ? __('ui.deactivate') : __('ui.activate') }}</span></button>
                                            </form>
                                        @endcan
                                    </span>
                                </td>
                            </tr>
                            @foreach ($parent->variants as $product)
                                @include('catalog.products._variant-row', ['product' => $product])
                            @endforeach
                        @empty
                            <tr><td colspan="{{ $type === 'softlens' ? 7 : 6 }}"><x-lentera.empty-state>{{ __('ui.products.empty') }}</x-lentera.empty-state></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $parents->links() }}</div>
        </div>
    </div>
</x-app-layout>
