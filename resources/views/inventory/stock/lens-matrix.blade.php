<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.matrix.title') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-lg border border-lentera-200 bg-lentera-50 px-4 py-3 text-sm text-lentera-800">{{ session('status') }}</div>
            @endif

            <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
                <div>
                    <x-input-label for="induk" :value="__('ui.matrix.parent')" />
                    <select id="induk" name="induk" class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                        @foreach ($parents as $p)
                            <option value="{{ $p->id }}" @selected($parent && $p->id === $parent->id)>{{ $p->name }}{{ $p->brand ? ' · '.$p->brand : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="cabang" :value="__('ui.matrix.branch')" />
                    <select id="cabang" name="cabang" class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                        @foreach ($branches as $b)
                            <option value="{{ $b->id }}" @selected($b->id === $branchId)>{{ $b->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="index" :value="__('ui.matrix.index')" />
                    <select id="index" name="index" class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                        <option value="">—</option>
                        @foreach ($options['indexes'] as $ix)
                            <option value="{{ $ix }}" @selected($index === $ix)>{{ $ix }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="coating" :value="__('ui.matrix.coating')" />
                    <select id="coating" name="coating" class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                        <option value="">—</option>
                        @foreach ($options['coatings'] as $ct)
                            <option value="{{ $ct }}" @selected($coating === $ct)>{{ $ct }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="eye_side" :value="__('ui.products.eye_side')" />
                    <select id="eye_side" name="eye_side" class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                        <option value="">—</option>
                        <option value="R" @selected($eyeSide === 'R')>R (OD)</option>
                        <option value="L" @selected($eyeSide === 'L')>L (OS)</option>
                        <option value="Both" @selected($eyeSide === 'Both')>Both</option>
                    </select>
                </div>
                <x-secondary-button type="submit">{{ __('ui.matrix.show') }}</x-secondary-button>
                @if ($parent)
                    <x-lentera.aksi-bar>
                        <x-lentera.menu-item :href="route('stock.lens-matrix.export', array_filter(['induk' => $parent->id, 'cabang' => $branchId, 'index' => $index, 'coating' => $coating, 'eye_side' => $eyeSide]))">{{ __('ui.matrix.export') }}</x-lentera.menu-item>
                        @can('po.create')
                            <x-lentera.menu-item :href="route('po.create', ['matriks_induk' => $parent->id, 'matriks_cabang' => $branchId])" primary>{{ __('ui.matrix.create_po') }}</x-lentera.menu-item>
                        @endcan
                    </x-lentera.aksi-bar>
                @endif
            </form>

            @if ($parent === null || $grid === null)
                <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                    <x-lentera.empty-state>{{ __('ui.matrix.no_products') }}</x-lentera.empty-state>
                </div>
            @else
                <div class="overflow-auto rounded-lg bg-white shadow-soft">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 text-left text-gray-500">
                                <th class="sticky left-0 bg-white px-3 py-2 font-medium">SPH \ {{ $grid['col_label'] }}</th>
                                @foreach ($grid['cols'] as $col)
                                    <th class="px-2 py-2 text-right font-medium tnum">{{ $matrix->display($col + 0) }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($grid['rows'] as $row)
                                <tr class="border-b border-gray-50 last:border-0">
                                    <th class="sticky left-0 bg-white px-3 py-1.5 text-left font-medium text-gray-700 tnum">{{ $row['label'] }}</th>
                                    @foreach ($grid['cols'] as $col)
                                        @php($cell = $row['cells'][$matrix->colKey($col + 0)] ?? null)
                                        <td class="px-2 py-1.5 text-right">
                                            @if ($cell)
                                                <span class="block text-[11px] leading-tight text-gray-400">{{ $cell['product']->sku }}</span>
                                                <span class="font-semibold {{ $cell['stock'] > 0 ? 'text-lentera-800' : 'text-red-500' }} tnum">{{ $cell['stock'] }}</span>
                                            @else
                                                <span class="text-gray-300" title="{{ __('ui.matrix.empty_cell') }}">—</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
