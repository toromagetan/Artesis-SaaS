<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ $supplier->name }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-lg border border-lentera-200 bg-lentera-50 px-4 py-3 text-sm text-lentera-800">{{ session('status') }}</div>
            @endif

            <div class="mb-4 flex items-center justify-between">
                <a href="{{ route('suppliers.index') }}" class="text-sm text-gray-600 underline">{{ __('ui.back') }}</a>
                <x-lentera.aksi-bar>
                    <x-lentera.menu-item :href="route('suppliers.edit', $supplier)">{{ __('ui.edit') }}</x-lentera.menu-item>

                    <form method="POST" action="{{ route('suppliers.toggle-active', $supplier) }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-sm font-medium shadow-soft transition duration-150 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-lentera-500 {{ $supplier->is_active ? 'text-red-600' : 'text-lentera-700' }}">{{ $supplier->is_active ? __('ui.deactivate') : __('ui.activate') }}</button>
                    </form>
                </x-lentera.aksi-bar>
            </div>

            <div class="mb-6 rounded-lg bg-white p-6 shadow-soft">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ __('ui.suppliers.contact_person') }}</p>
                        <p class="mt-1 text-sm font-medium text-gray-800">{{ $supplier->contact_person ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ __('ui.suppliers.phone') }}</p>
                        <p class="mt-1 text-sm font-medium text-gray-800">{{ $supplier->phone ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ __('ui.suppliers.email') }}</p>
                        <p class="mt-1 text-sm font-medium text-gray-800">{{ $supplier->email ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ __('ui.suppliers.status') }}</p>
                        <p class="mt-1"><x-lentera.badge :active="$supplier->is_active" /></p>
                    </div>
                </div>
                <div class="mt-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ __('ui.suppliers.address') }}</p>
                    <p class="mt-1 text-sm text-gray-600">{{ $supplier->address ?? '—' }}</p>
                </div>
            </div>

            <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-lg bg-white p-5 shadow-soft">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ __('ui.suppliers.outstanding') }}</p>
                    <p class="mt-1 text-lg font-semibold tnum text-lentera-950">{{ $outstanding->format() }}</p>
                </div>
                <div class="rounded-lg bg-white p-5 shadow-soft">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ __('ui.suppliers.credit') }}</p>
                    <p class="mt-1 text-lg font-semibold tnum text-lentera-950">{{ $credit->format() }}</p>
                </div>
                <div class="rounded-lg bg-white p-5 shadow-soft">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ __('ui.suppliers.po_count') }}</p>
                    <p class="mt-1 text-lg font-semibold tnum text-lentera-950">{{ $poCount }}</p>
                </div>
                <div class="rounded-lg bg-white p-5 shadow-soft">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ __('ui.suppliers.open_receipts') }}</p>
                    <p class="mt-1 text-lg font-semibold tnum text-lentera-950">{{ count($openReceipts) }}</p>
                </div>
            </div>

            @if (count($openReceipts) > 0)
                <h2 class="mb-3 text-base font-semibold text-lentera-950">{{ __('ui.suppliers.open_receipts') }}</h2>
                <div class="mb-6 overflow-hidden rounded-lg bg-white shadow-soft">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 text-left text-gray-500">
                                <th class="px-4 py-3 font-medium">{{ __('ui.suppliers.receipt_number') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('ui.suppliers.po_number') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('ui.suppliers.date') }}</th>
                                <th class="px-4 py-3 font-medium text-right">{{ __('ui.suppliers.receipt_total') }}</th>
                                <th class="px-4 py-3 font-medium text-right">{{ __('ui.suppliers.allocated') }}</th>
                                <th class="px-4 py-3 font-medium text-right">{{ __('ui.suppliers.remaining') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($openReceipts as $receipt)
                                <tr class="border-b border-gray-50 last:border-0">
                                    <td class="px-4 py-3 font-medium text-gray-800">{{ $receipt['number'] }}</td>
                                    <td class="px-4 py-3 text-gray-600">{{ $receipt['po_number'] }}</td>
                                    <td class="px-4 py-3 text-gray-600">{{ $receipt['date']->format('d/m/Y') }}</td>
                                    <td class="px-4 py-3 text-right tnum">{{ $receipt['total']->format() }}</td>
                                    <td class="px-4 py-3 text-right tnum">{{ $receipt['allocated']->format() }}</td>
                                    <td class="px-4 py-3 text-right tnum">{{ $receipt['remaining']->format() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <h2 class="mb-3 text-base font-semibold text-lentera-950">{{ __('ui.suppliers.po_history') }}</h2>
            <div class="mb-6 overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.suppliers.po_number') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.suppliers.date') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.suppliers.branch') }}</th>
                            <th class="px-4 py-3 font-medium text-right">{{ __('ui.suppliers.po_total') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.suppliers.status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($orders as $order)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 font-medium text-gray-800">
                                    <a href="{{ route('po.show', $order) }}" class="text-lentera-800 underline decoration-lentera-300 underline-offset-2 hover:text-lentera-950">{{ $order->number }}</a>
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ $order->order_date->format('d/m/Y') }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $order->branch?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-right tnum">{{ \App\Support\Money::fromDecimal((string) $order->total_amount)->format() }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ __('ui.purchasing.po_statuses.'.$order->status) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><x-lentera.empty-state>{{ __('ui.suppliers.no_orders') }}</x-lentera.empty-state></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mb-6">{{ $orders->links() }}</div>

            <h2 class="mb-3 text-base font-semibold text-lentera-950">{{ __('ui.suppliers.payment_history') }}</h2>
            <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.suppliers.payment_number') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.suppliers.date') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.suppliers.method') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.suppliers.reference') }}</th>
                            <th class="px-4 py-3 font-medium text-right">{{ __('ui.suppliers.amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($payments as $payment)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 font-medium text-gray-800">{{ $payment->number }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $payment->payment_date->format('d/m/Y') }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $payment->method }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $payment->reference ?? '—' }}</td>
                                <td class="px-4 py-3 text-right tnum">{{ \App\Support\Money::fromDecimal((string) $payment->amount)->format() }}</td>
                                <td class="px-4 py-3">
                                    <x-lentera.aksi-baris>
                                        <x-lentera.menu-item :href="route('payments.print', $payment)" target="_blank" print>{{ __('ui.print') }}</x-lentera.menu-item>
                                    </x-lentera.aksi-baris>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><x-lentera.empty-state>{{ __('ui.suppliers.no_payments') }}</x-lentera.empty-state></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $payments->links() }}</div>
        </div>
    </div>
</x-app-layout>
