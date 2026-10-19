<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.receivables.title') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-lg border border-lentera-200 bg-lentera-50 px-4 py-3 text-sm text-lentera-800">{{ session('status') }}</div>
            @endif

            <div class="mb-4 flex items-center justify-between">
                <form method="GET" class="flex gap-2">
                    <select name="cabang" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                        <option value="">{{ __('ui.sales.branch') }}</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected((string) $branchId === (string) $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                    <select name="umur" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                        <option value="">{{ __('ui.receivables.age') }}</option>
                        <option value="current" @selected($bucket === 'current')>{{ __('ui.receivables.age_current') }}</option>
                        <option value="d30" @selected($bucket === 'd30')>{{ __('ui.receivables.age_d30') }}</option>
                        <option value="d60" @selected($bucket === 'd60')>{{ __('ui.receivables.age_d60') }}</option>
                        <option value="d90" @selected($bucket === 'd90')>{{ __('ui.receivables.age_d90') }}</option>
                    </select>
                    <x-secondary-button type="submit">{{ __('ui.search') }}</x-secondary-button>
                </form>
                @can('reports.export')
                    <x-lentera.dropdown-aksi>
                        <x-lentera.menu-item :href="route('receivables.index', array_merge(request()->query(), ['ekspor' => 1]))">{{ __('ui.receivables.export') }}</x-lentera.menu-item>
                        <x-lentera.menu-item :href="route('receivables.index', array_merge(request()->query(), ['format' => 'xlsx']))">{{ __('ui.reports.export_xlsx') }}</x-lentera.menu-item>
                    </x-lentera.dropdown-aksi>
                @endcan
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.receivables.customer') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.receivables.sale_number') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.receivables.total') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.receivables.paid') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.receivables.remaining') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.receivables.due_date') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.receivables.age') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.receivables.status') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            @php $sale = $row['sale']; @endphp
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 font-medium text-gray-800">{{ $sale->customer->name }}</td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('sales.show', $sale) }}" class="text-lentera-800 underline-offset-2 hover:underline">{{ $sale->number }}</a>
                                </td>
                                <td class="px-4 py-3 tnum">{{ \App\Support\Money::fromDecimal((string) $sale->total_amount)->format() }}</td>
                                <td class="px-4 py-3 tnum">{{ \App\Support\Money::fromDecimal((string) $sale->paid_amount)->format() }}</td>
                                <td class="px-4 py-3 tnum font-medium">{{ $sale->remaining()->format() }}</td>
                                <td class="px-4 py-3">{{ $row['due_date']->format('d/m/Y') }}</td>
                                <td class="px-4 py-3">{{ $row['age_days'] }} {{ __('ui.receivables.days') }}</td>
                                <td class="px-4 py-3">
                                    @if ($row['bucket'] === 'current')
                                        <span class="rounded bg-lentera-50 px-2 py-0.5 text-xs font-medium text-lentera-800">{{ __('ui.receivables.age_current') }}</span>
                                    @elseif ($row['bucket'] === 'd30')
                                        <span class="rounded bg-gold/10 px-2 py-0.5 text-xs font-medium text-gold-dark">{{ __('ui.receivables.age_d30') }}</span>
                                    @else
                                        <span class="rounded bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700">{{ __('ui.receivables.age_'.$row['bucket']) }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <x-lentera.row-actions>
                                        @can('sales.pay')
                                            <x-lentera.menu-item :href="route('sales.payment', $sale)">{{ __('ui.receivables.receive') }}</x-lentera.menu-item>
                                        @endcan
                                        <x-lentera.menu-item :href="route('sales.show', $sale)">{{ __('ui.receivables.view_sale') }}</x-lentera.menu-item>
                                    </x-lentera.row-actions>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9"><x-lentera.empty-state>{{ __('ui.receivables.empty') }}</x-lentera.empty-state></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $rows->links() }}</div>
        </div>
    </div>
</x-app-layout>
