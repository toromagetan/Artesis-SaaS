<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.analytics.title') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
            <div>
                @include('reporting.partials.tabs')
                @include('reporting.partials.filters')
            </div>

            @isset($suppliers)
                <section class="overflow-hidden rounded-lg bg-white shadow-soft">
                    <h2 class="border-b border-gray-100 px-4 py-3 font-semibold text-gray-800">{{ __('ui.analytics.suppliers') }}</h2>
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 text-left text-gray-500">
                                <th class="px-4 py-3 font-medium">Supplier</th>
                                <th class="px-4 py-3 text-right font-medium">{{ __('ui.analytics.po_count') }}</th>
                                <th class="px-4 py-3 text-right font-medium">{{ __('ui.analytics.lead_time') }}</th>
                                <th class="px-4 py-3 text-right font-medium">{{ __('ui.analytics.purchase_value') }}</th>
                                <th class="px-4 py-3 text-right font-medium">{{ __('ui.analytics.received_qty') }}</th>
                                <th class="px-4 py-3 text-right font-medium">{{ __('ui.analytics.return_rate') }}</th>
                                <th class="px-4 py-3 text-right font-medium">{{ __('ui.analytics.fulfillment') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($suppliers as $row)
                                <tr class="border-b border-gray-50 last:border-0">
                                    <td class="px-4 py-3 font-medium text-gray-800">{{ $row['supplier_name'] }}</td>
                                    <td class="px-4 py-3 text-right tnum">{{ $row['po_count'] }}</td>
                                    <td class="px-4 py-3 text-right tnum">{{ $row['lead_time_days'] !== null ? $row['lead_time_days'].' '.__('ui.analytics.days') : '—' }}</td>
                                    <td class="px-4 py-3 text-right tnum">{{ $row['purchase_value']->format() }}</td>
                                    <td class="px-4 py-3 text-right tnum">{{ $row['received_qty'] }}</td>
                                    <td class="px-4 py-3 text-right tnum">{{ number_format($row['return_rate_bp'] / 100, 2) }}%</td>
                                    <td class="px-4 py-3 text-right tnum">{{ number_format($row['fulfillment_bp'] / 100, 2) }}%</td>
                                </tr>
                            @empty
                                <tr><td colspan="7"><x-lentera.empty-state>{{ __('ui.analytics.empty') }}</x-lentera.empty-state></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </section>
            @endisset

            <section class="overflow-hidden rounded-lg bg-white shadow-soft">
                <h2 class="border-b border-gray-100 px-4 py-3 font-semibold text-gray-800">{{ __('ui.analytics.top_products') }}</h2>
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.products.name') }}</th>
                            <th class="px-4 py-3 font-medium">SKU</th>
                            <th class="px-4 py-3 text-right font-medium">Qty</th>
                            <th class="px-4 py-3 text-right font-medium">{{ __('ui.analytics.revenue') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($sales['top_products'] as $row)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 font-medium text-gray-800">{{ $row['name'] }}</td>
                                <td class="px-4 py-3">{{ $row['sku'] }}</td>
                                <td class="px-4 py-3 text-right tnum">{{ $row['qty'] }}</td>
                                <td class="px-4 py-3 text-right tnum">{{ $row['revenue']->format() }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4"><x-lentera.empty-state>{{ __('ui.analytics.empty') }}</x-lentera.empty-state></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </section>

            <section class="overflow-hidden rounded-lg bg-white shadow-soft">
                <h2 class="border-b border-gray-100 px-4 py-3 font-semibold text-gray-800">{{ __('ui.analytics.category_margins') }}</h2>
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.analytics.category') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ __('ui.analytics.revenue') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ __('ui.analytics.cogs') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ __('ui.analytics.margin') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($sales['category_margins'] as $row)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 font-medium text-gray-800">{{ __('ui.products.types.'.$row['type']) }}</td>
                                <td class="px-4 py-3 text-right tnum">{{ $row['revenue']->format() }}</td>
                                <td class="px-4 py-3 text-right tnum">{{ $row['cost']->format() }}</td>
                                <td class="px-4 py-3 text-right font-medium tnum">{{ $row['margin']->format() }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4"><x-lentera.empty-state>{{ __('ui.analytics.empty') }}</x-lentera.empty-state></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </section>

            <div class="grid gap-6 lg:grid-cols-2">
                <section class="overflow-hidden rounded-lg bg-white shadow-soft">
                    <h2 class="border-b border-gray-100 px-4 py-3 font-semibold text-gray-800">{{ __('ui.analytics.branch_trend') }}</h2>
                    <table class="min-w-full text-sm">
                        <tbody>
                            @forelse ($sales['branch_trend'] as $row)
                                <tr class="border-b border-gray-50 last:border-0">
                                    <td class="px-4 py-3 font-medium text-gray-800">{{ \App\Modules\Master\Models\Branch::find($row['branch_id'])?->name }}</td>
                                    <td class="px-4 py-3 tnum">{{ $row['month'] }}</td>
                                    <td class="px-4 py-3 text-right tnum">{{ $row['sales_count'] }}</td>
                                    <td class="px-4 py-3 text-right tnum">{{ $row['revenue']->format() }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4"><x-lentera.empty-state>{{ __('ui.analytics.empty') }}</x-lentera.empty-state></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </section>

                <section class="overflow-hidden rounded-lg bg-white shadow-soft">
                    <h2 class="border-b border-gray-100 px-4 py-3 font-semibold text-gray-800">{{ __('ui.analytics.top_customers') }}</h2>
                    <table class="min-w-full text-sm">
                        <tbody>
                            @forelse ($sales['top_customers'] as $row)
                                <tr class="border-b border-gray-50 last:border-0">
                                    <td class="px-4 py-3 font-medium text-gray-800">{{ $row['name'] }}</td>
                                    <td class="px-4 py-3 text-right tnum">{{ $row['sales_count'] }}</td>
                                    <td class="px-4 py-3 text-right tnum">{{ $row['revenue']->format() }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3"><x-lentera.empty-state>{{ __('ui.analytics.empty') }}</x-lentera.empty-state></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </section>
            </div>
        </div>
    </div>
</x-app-layout>
