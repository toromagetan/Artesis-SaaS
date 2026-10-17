<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.stock.title') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="mb-4 flex items-center justify-between">
                <form method="GET" class="flex gap-2">
                    <x-text-input name="q" :value="$q" :placeholder="__('ui.stock.search_placeholder')" class="w-64" />
                    <select name="cabang" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected($branchId === $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                    <select name="tipe" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                        <option value="">—</option>
                        @foreach (\App\Modules\Catalog\Models\Product::TYPES as $t)
                            <option value="{{ $t }}" @selected($type === $t)>{{ __('ui.products.types.'.$t) }}</option>
                        @endforeach
                    </select>
                    <x-secondary-button type="submit">{{ __('ui.search') }}</x-secondary-button>
                </form>
                <a href="{{ route('stock.movements') }}" class="text-sm text-lentera-800 underline">{{ __('ui.stock.movements') }}</a>
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.stock.product') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.products.sku') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.stock.qty') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.stock.value') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.stock.status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 font-medium text-gray-800">
                                    <a href="{{ route('products.show', $row['product']) }}" class="text-lentera-800 underline-offset-2 hover:underline">{{ $row['product']->name }}</a>
                                    <span class="block text-xs text-gray-400">{{ $row['product']->typeLabel() }}</span>
                                </td>
                                <td class="px-4 py-3">{{ $row['product']->sku }}</td>
                                <td class="px-4 py-3 tnum font-medium {{ $row['qty'] < 0 ? 'text-red-600' : '' }}">{{ $row['qty'] }}</td>
                                <td class="px-4 py-3 tnum">{{ $row['value']->format() }}</td>
                                <td class="px-4 py-3">
                                    @switch($row['status'])
                                        @case('safe') <span class="inline-flex items-center rounded-full bg-lentera-50 px-2 py-0.5 text-xs font-semibold text-lentera-700">{{ __('ui.stock.status_safe') }}</span> @break
                                        @case('low') <span class="inline-flex items-center rounded-full bg-gold/10 px-2 py-0.5 text-xs font-semibold text-gold-dark">{{ __('ui.stock.status_low') }}</span> @break
                                        @case('empty') <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-500">{{ __('ui.stock.status_empty') }}</span> @break
                                        @case('backorder') <span class="inline-flex items-center rounded-full bg-red-50 px-2 py-0.5 text-xs font-semibold text-red-600">{{ __('ui.stock.status_backorder') }}</span> @break
                                    @endswitch
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><x-lentera.empty-state>{{ __('ui.stock.empty') }}</x-lentera.empty-state></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
