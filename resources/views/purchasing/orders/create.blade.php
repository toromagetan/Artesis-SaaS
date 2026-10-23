<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.purchasing.po_create') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-4xl sm:px-6 lg:px-8">
            @if ($errors->any())
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif
            @if ($lensParents->isNotEmpty())
                <div class="mb-4 rounded-lg bg-white p-6 shadow-soft">
                    <h2 class="text-sm font-semibold text-gray-700">{{ __('ui.matrix.title') }}</h2>
                    <p class="mt-1 text-sm text-gray-500">{{ __('ui.matrix.po_help') }}</p>
                    <form method="GET" action="{{ route('po.create') }}" class="mt-3 flex flex-wrap items-end gap-3">
                        <div>
                            <x-input-label for="matriks_induk" :value="__('ui.matrix.parent')" />
                            <select id="matriks_induk" name="matriks_induk" class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                                <option value="">—</option>
                                @foreach ($lensParents as $parent)
                                    <option value="{{ $parent->id }}" @selected($matrixParent?->id === $parent->id)>{{ $parent->brand ? $parent->brand.' ' : '' }}{{ $parent->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="matriks_cabang" :value="__('ui.matrix.branch')" />
                            <select id="matriks_cabang" name="matriks_cabang" class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                                @foreach ($branches as $branch)
                                    <option value="{{ $branch->id }}" @selected($matrixBranch?->id === $branch->id)>{{ $branch->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        @if ($matrixParent !== null)
                            <div>
                                <x-input-label for="matriks_index" :value="__('ui.matrix.index')" />
                                <select id="matriks_index" name="matriks_index" class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                                    <option value="">—</option>
                                    @foreach ($matrixOptions['indexes'] as $idx)
                                        <option value="{{ $idx }}" @selected($matrixIndex === $idx)>{{ $idx }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <x-input-label for="matriks_coating" :value="__('ui.matrix.coating')" />
                                <select id="matriks_coating" name="matriks_coating" class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                                    <option value="">—</option>
                                    @foreach ($matrixOptions['coatings'] as $coat)
                                        <option value="{{ $coat }}" @selected($matrixCoating === $coat)>{{ $coat }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                        <x-secondary-button type="submit">{{ __('ui.matrix.show') }}</x-secondary-button>
                    </form>
                </div>
            @endif
            <div class="rounded-lg bg-white p-6 shadow-soft">
                <form method="POST" action="{{ route('po.store') }}" @if ($matrix !== null) x-data="{ sel: {} }" @endif>
                    @csrf
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <x-input-label for="supplier_id" :value="__('ui.purchasing.supplier')" />
                            <select id="supplier_id" name="supplier_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500" required>
                                @foreach ($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="cabang_tujuan" :value="__('ui.purchasing.destination_branch')" />
                            <select id="cabang_tujuan" name="cabang_tujuan" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500" required>
                                @foreach ($branches as $branch)
                                    <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="order_date" :value="__('ui.purchasing.date')" />
                            <x-text-input id="order_date" name="order_date" type="date" class="mt-1 block w-full" :value="now()->toDateString()" />
                        </div>
                    </div>

                    <h2 class="mb-2 mt-6 text-sm font-semibold text-gray-700">{{ __('ui.purchasing.items') }}</h2>
                    <div class="space-y-2">
                        @for ($i = 0; $i < 4; $i++)
                            <div class="grid grid-cols-12 gap-2">
                                <div class="col-span-6">
                                    <select name="items[{{ $i }}][product_id]" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                                        <option value="">— {{ __('ui.purchasing.product') }} —</option>
                                        @foreach ($products as $product)
                                            <option value="{{ $product->id }}">{{ $product->name }} ({{ $product->sku }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-span-2">
                                    <x-text-input name="items[{{ $i }}][qty]" type="number" min="1" class="block w-full tnum" placeholder="{{ __('ui.purchasing.qty') }}" />
                                </div>
                                <div class="col-span-4">
                                    <x-text-input name="items[{{ $i }}][unit_cost]" type="number" step="0.01" min="0" class="block w-full tnum" :placeholder="__('ui.purchasing.unit_cost')" />
                                </div>
                            </div>
                        @endfor
                    </div>

                    @if ($matrix !== null && $matrixParent !== null)
                        <input type="hidden" name="matrix_parent_id" value="{{ $matrixParent->id }}">
                        <input type="hidden" name="matrix_index" value="{{ $matrixIndex }}">
                        <input type="hidden" name="matrix_coating" value="{{ $matrixCoating }}">
                        <input type="hidden" name="matrix_selection" :value="JSON.stringify(sel)">
                        <h2 class="mb-1 mt-8 text-sm font-semibold text-gray-700">{{ __('ui.matrix.title') }} — {{ $matrixParent->brand ? $matrixParent->brand.' ' : '' }}{{ $matrixParent->name }} · {{ $matrixBranch->name }}</h2>
                        <p class="mb-2 text-xs text-gray-500">{{ __('ui.matrix.po_grid_help') }}</p>
                        <p class="mb-2 text-xs"><a href="{{ route('po.matrix-export', array_filter(['matriks_induk' => $matrixParent->id, 'matriks_cabang' => $matrixBranch->id, 'matriks_index' => $matrixIndex, 'matriks_coating' => $matrixCoating])) }}" class="text-lentera-800 underline">{{ __('ui.reports.export_xlsx') }}</a></p>
                        <div class="max-h-[70vh] overflow-auto">
                            <table class="min-w-full border-collapse text-sm">
                                <thead>
                                    <tr class="text-left text-gray-500">
                                        <th class="sticky left-0 top-0 z-10 border border-gray-100 bg-white px-2 py-1.5 font-medium">SPH \ {{ $matrix['col_label'] }}</th>
                                        @foreach ($matrix['cols'] as $col)
                                            <th class="sticky top-0 border border-gray-100 bg-white px-2 py-1.5 text-right font-medium tnum">{{ $matrixService->display($col) }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($matrix['rows'] as $row)
                                        <tr>
                                            <th class="sticky left-0 border border-gray-100 bg-white px-2 py-1.5 text-left font-medium text-gray-600 tnum">{{ $row['label'] }}</th>
                                            @foreach ($matrix['cols'] as $col)
                                                @php $cell = $row['cells'][$matrixService->colKey($col)] ?? null; @endphp
                                                <td class="border border-gray-100 px-2 py-1.5 align-top">
                                                    @if ($cell !== null)
                                                        <div class="flex items-center justify-between gap-2 text-xs text-gray-500">
                                                            <span>{{ $cell['product']->sku }}</span>
                                                            <span>{{ __('ui.matrix.stock') }}: <span class="tnum">{{ $cell['stock'] }}</span></span>
                                                        </div>
                                                        <input type="number" min="0" class="mt-0.5 block w-20 rounded-md border-gray-300 text-right text-sm shadow-sm focus:border-lentera-500 focus:ring-lentera-500" placeholder="0" @input="sel['p{{ $cell['product']->id }}'] = $event.target.value">
                                                    @else
                                                        <div class="text-xs text-gray-300">{{ __('ui.matrix.empty_cell') }}</div>
                                                        <input type="number" min="0" class="mt-0.5 block w-20 rounded-md border-gray-200 text-right text-sm shadow-sm focus:border-lentera-500 focus:ring-lentera-500" placeholder="0" @input="sel['n|{{ number_format($row['sph'], 2, '.', '') }}|{{ number_format($col, 2, '.', '') }}|{{ $row['eye'] ?? '' }}'] = $event.target.value">
                                                    @endif
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    <div class="mt-6 flex items-center gap-3">
                        <a href="{{ route('po.index') }}" class="text-sm text-gray-600 underline">{{ __('ui.back') }}</a>
                        <x-primary-button>{{ __('ui.save') }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
