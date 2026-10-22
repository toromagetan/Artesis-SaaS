<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.opname.title') }} — {{ $opname->branch->name }} · {{ $opname->scheduled_date->format('d/m/Y') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-lg border border-lentera-200 bg-lentera-50 px-4 py-3 text-sm text-lentera-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
            @endif

            <div class="mb-4 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-semibold {{ $opname->status === 'approved' ? 'border-lentera-200 bg-lentera-50 text-lentera-700' : 'border-gray-200 bg-gray-100 text-gray-500' }}">{{ __('ui.opname.statuses.'.$opname->status) }}</span>
                    @if ($opname->isLocking())
                        <span class="rounded-md border border-gold/50 bg-gold/10 px-2 py-1 text-xs font-medium text-gold-dark">{{ __('ui.opname.lock_notice') }}</span>
                    @endif
                </div>
                <x-lentera.aksi-bar>
                    @if ($opname->status === 'scheduled')
                        <form method="POST" action="{{ route('opnames.start', $opname) }}">
                            @csrf
                            <button type="submit" class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-sm font-medium text-gray-700 shadow-soft transition duration-150 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-lentera-500">{{ __('ui.opname.start') }}</button>
                        </form>
                    @endif
                    @if ($opname->status === 'counting')
                        <form method="POST" action="{{ route('opnames.review', $opname) }}">
                            @csrf
                            <button type="submit" class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-sm font-medium text-gray-700 shadow-soft transition duration-150 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-lentera-500">{{ __('ui.opname.submit_review') }}</button>
                        </form>
                    @endif
                    @if ($opname->status === 'review')
                        <form method="POST" action="{{ route('opnames.approve', $opname) }}">
                            @csrf
                            <button type="submit" class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-sm font-medium text-gray-700 shadow-soft transition duration-150 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-lentera-500">{{ __('ui.opname.approve') }}</button>
                        </form>
                    @endif
                    @if ($opname->status === 'counting' && auth()->user()->hasRole('Owner'))

                        <form method="POST" action="{{ route('opnames.cancel', $opname) }}" onsubmit="return confirm('{{ __('ui.opname.cancel_confirm') }}')">
                            @csrf
                            <button type="submit" class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-red-300 bg-white px-3.5 py-2 text-sm font-medium text-red-600 shadow-soft transition duration-150 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-lentera-500">{{ __('ui.opname.cancel') }}</button>
                        </form>
                    @endif
                </x-lentera.aksi-bar>
            </div>

            <div class="mb-4 grid gap-4 text-sm sm:grid-cols-4">
                <div class="rounded-lg bg-white p-4 shadow-soft"><p class="text-gray-500">{{ __('ui.opname.branch') }}</p><p class="font-medium">{{ $opname->branch->name }}</p></div>
                <div class="rounded-lg bg-white p-4 shadow-soft"><p class="text-gray-500">{{ __('ui.opname.created_by') }}</p><p class="font-medium">{{ $opname->creator->name }}</p></div>
                <div class="rounded-lg bg-white p-4 shadow-soft"><p class="text-gray-500">{{ __('ui.opname.approved_by') }}</p><p class="font-medium">{{ $opname->approver?->name ?? '—' }}</p></div>
                <div class="rounded-lg bg-white p-4 shadow-soft"><p class="text-gray-500">{{ __('ui.opname.notes') }}</p><p class="font-medium">{{ $opname->notes ?? '—' }}</p></div>
            </div>

            @if ($opname->status === 'counting')
                <form method="POST" action="{{ route('opnames.count', $opname) }}">
                    @csrf
            @endif
            <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.opname.product') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.opname.sku') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ __('ui.opname.system_qty') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ __('ui.opname.counted_qty') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ __('ui.opname.difference') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($flatItems as $item)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3">{{ $item->product->name }}</td>
                                <td class="px-4 py-3">{{ $item->product->sku }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ $item->system_qty }}</td>
                                <td class="px-4 py-3 text-right">
                                    @if ($opname->status === 'counting')
                                        <input type="number" min="0" name="counts[{{ $item->id }}]" value="{{ $item->counted_qty }}" class="w-24 rounded-md border-gray-300 text-right text-sm shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                                    @else
                                        <span class="tabular-nums">{{ $item->counted_qty ?? '—' }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums {{ $item->difference !== 0 ? 'font-semibold text-red-600' : '' }}">{{ $item->counted_qty === null ? '—' : ($item->difference > 0 ? '+'.$item->difference : $item->difference) }}</td>
                            </tr>
                        @empty
                            @if (empty($matrixGroups))
                                <tr><td colspan="5"><x-lentera.empty-state>{{ __('ui.opname.no_items') }}</x-lentera.empty-state></td></tr>
                            @endif
                        @endforelse
                    </tbody>
                </table>
            </div>

            @foreach ($matrixGroups as $group)
                <div class="mt-4 overflow-hidden rounded-lg bg-white p-4 shadow-soft">
                    <h2 class="text-sm font-semibold text-gray-700">{{ __('ui.matrix.title') }} — {{ $group['parent']->brand ? $group['parent']->brand.' ' : '' }}{{ $group['parent']->name }}</h2>
                    <p class="mb-2 mt-0.5 text-xs text-gray-500">{{ __('ui.matrix.opname_help') }}</p>
                    <div class="overflow-x-auto">
                        <table class="min-w-full border-collapse text-sm">
                            <thead>
                                <tr class="text-left text-gray-500">
                                    <th class="border border-gray-100 px-2 py-1.5 font-medium">SPH \ {{ $group['col_label'] }}</th>
                                    @foreach ($group['cols'] as $col)
                                        <th class="border border-gray-100 px-2 py-1.5 text-right font-medium tnum">{{ $matrixService->display($col) }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($group['rows'] as $row)
                                    <tr>
                                        <th class="border border-gray-100 px-2 py-1.5 text-left font-medium text-gray-600 tnum">{{ $row['label'] }}</th>
                                        @foreach ($group['cols'] as $col)
                                            <td class="border border-gray-100 px-2 py-1.5 align-top">
                                                @foreach ($row['cells'][$matrixService->colKey($col)] ?? [] as $item)
                                                    <div class="mb-1 last:mb-0">
                                                        <div class="flex items-center justify-between gap-2 text-xs text-gray-500">
                                                            <span>{{ $item->product->sku }}@if ($item->product->eye_side) · {{ $item->product->eye_side }}@endif</span>
                                                            <span>{{ __('ui.opname.system_qty') }}: <span class="tnum">{{ $item->system_qty }}</span></span>
                                                        </div>
                                                        @if ($opname->status === 'counting')
                                                            <input type="number" min="0" name="counts[{{ $item->id }}]" value="{{ $item->counted_qty }}" class="mt-0.5 block w-20 rounded-md border-gray-300 text-right text-sm shadow-sm focus:border-lentera-500 focus:ring-lentera-500" placeholder="0">
                                                        @else
                                                            <div class="text-right text-sm tabular-nums">{{ $item->counted_qty ?? '—' }}</div>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
            @if ($opname->status === 'counting')
                    <div class="mt-4 flex items-center gap-3">
                        <x-primary-button>{{ __('ui.opname.save_counts') }}</x-primary-button>
                        <a href="{{ route('opnames.index') }}" class="text-sm text-gray-500 hover:text-gray-700">{{ __('ui.back') }}</a>
                    </div>
                </form>
            @else
                <div class="mt-4"><a href="{{ route('opnames.index') }}" class="text-sm text-gray-500 hover:text-gray-700">{{ __('ui.back') }}</a></div>
            @endif
        </div>
    </div>
</x-app-layout>
