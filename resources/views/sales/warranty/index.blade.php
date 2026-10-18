<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.warranty.title') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-lg border border-lentera-200 bg-lentera-50 px-4 py-3 text-sm text-lentera-800">{{ session('status') }}</div>
            @endif

            <div class="flex items-center justify-between">
                <form method="GET" class="flex items-center gap-2">
                    <select name="status" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-lentera-500 focus:ring-lentera-500" onchange="this.form.submit()">
                        <option value="">{{ __('ui.warranty.all_status') }}</option>
                        @foreach (['open', 'waiting_warehouse', 'old_item_received', 'replacement_preparing', 'replacement_shipped', 'replacement_received', 'completed'] as $st)
                            <option value="{{ $st }}" @selected(request('status') === $st)>{{ __('ui.warranty.status_'.$st) }}</option>
                        @endforeach
                    </select>
                </form>
                @can('sales.create')
                    <x-lentera.dropdown-aksi >
                        <x-lentera.menu-item :href="route('warranties.create')">{{ __('ui.warranty.create') }}</x-lentera.menu-item>
                    </x-lentera.dropdown-aksi>
                @endcan
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.warranty.number') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.warranty.invoice') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.warranty.customer') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.warranty.item') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.warranty.status') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.warranty.branch') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($cases as $case)
                            <tr class="border-b border-gray-50 last:border-0 hover:bg-lentera-50/50">
                                <td class="px-4 py-3"><a href="{{ route('warranties.show', $case) }}" class="font-medium text-lentera-700 hover:underline">{{ $case->number }}</a></td>
                                <td class="px-4 py-3">{{ $case->sale->number }}</td>
                                <td class="px-4 py-3">{{ $case->sale->customer->name ?? '—' }}</td>
                                <td class="px-4 py-3">{{ $case->saleItem->product_name_snapshot }}</td>
                                <td class="px-4 py-3"><span class="inline-flex items-center rounded-full bg-lentera-50 px-2.5 py-0.5 text-xs font-medium text-lentera-800 ring-1 ring-inset ring-lentera-600/20">{{ __('ui.warranty.status_'.$case->status) }}</span></td>
                                <td class="px-4 py-3">{{ $case->branch->name }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><x-lentera.empty-state>{{ __('ui.warranty.empty') }}</x-lentera.empty-state></td></tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="px-4 py-3">{{ $cases->links() }}</div>
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                <h2 class="px-6 pt-5 font-semibold text-gray-800">{{ __('ui.warranty.broken_title') }} — {{ $warehouse->name }}</h2>
                <table class="mt-3 min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.warranty.col_product') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.warranty.col_case') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.warranty.col_qty') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.warranty.col_status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($brokenPending as $broken)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3">{{ $broken->product->name }}</td>
                                <td class="px-4 py-3">@if ($broken->warrantyCase)<a href="{{ route('warranties.show', $broken->warrantyCase) }}" class="text-lentera-700 hover:underline">{{ $broken->warrantyCase->number }}</a>@else — @endif</td>
                                <td class="px-4 py-3 tnum">{{ $broken->quantity }}</td>
                                <td class="px-4 py-3">{{ $broken->status }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4"><x-lentera.empty-state>{{ __('ui.warranty.broken_empty') }}</x-lentera.empty-state></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
