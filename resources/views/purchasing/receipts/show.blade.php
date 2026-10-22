<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ $receipt->number }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-lg border border-lentera-200 bg-lentera-50 px-4 py-3 text-sm text-lentera-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            <div class="mb-4 flex items-center justify-between">
                <a href="{{ route('po.show', $receipt->purchaseOrder) }}" class="text-sm text-gray-600 underline">{{ __('ui.back') }} — {{ $receipt->purchaseOrder->number }}</a>
                <x-lentera.aksi-bar>
                    @can('payments.create')
                        @if ($receipt->paymentStatus() !== 'paid')
                            <x-lentera.menu-item :href="route('receipts.pay', $receipt)">{{ __('ui.purchasing.pay') }}</x-lentera.menu-item>
                        @endif
                    @endcan
                    @can('purchases.return')
                        <x-lentera.menu-item :href="route('receipts.return', $receipt)">{{ __('ui.purchasing.make_return') }}</x-lentera.menu-item>
                    @endcan
                </x-lentera.aksi-bar>
            </div>

            <div class="mb-6 rounded-lg bg-white p-6 shadow-soft">
                <dl class="grid grid-cols-[180px_1fr] gap-y-3 text-sm">
                    <dt class="text-gray-500">{{ __('ui.purchasing.supplier') }}</dt>
                    <dd class="font-medium text-gray-800">{{ $receipt->purchaseOrder->supplier->name }}</dd>
                    <dt class="text-gray-500">{{ __('ui.purchasing.received_date') }}</dt>
                    <dd class="font-medium text-gray-800">{{ $receipt->received_date->format('d/m/Y') }}</dd>
                    <dt class="text-gray-500">{{ __('ui.purchasing.delivery_note') }}</dt>
                    <dd class="font-medium text-gray-800">{{ $receipt->delivery_note_no ?: '—' }}</dd>
                    <dt class="text-gray-500">{{ __('ui.purchasing.destination_branch') }}</dt>
                    <dd class="font-medium text-gray-800">{{ $receipt->purchaseOrder->branch->name }}</dd>
                    <dt class="text-gray-500">{{ __('ui.purchasing.total') }}</dt>
                    <dd class="font-medium text-gray-800 tnum">{{ $receipt->totalAmount()->format() }}</dd>
                    <dt class="text-gray-500">{{ __('ui.purchasing.remaining') }}</dt>
                    <dd class="font-medium text-gray-800 tnum">{{ $receipt->remainingAmount()->format() }}</dd>
                    <dt class="text-gray-500">{{ __('ui.purchasing.pay_status') }}</dt>
                    <dd class="font-medium text-gray-800">{{ __('ui.purchasing.pay_statuses.'.$receipt->paymentStatus()) }}</dd>
                </dl>
            </div>

            <h2 class="mb-2 text-base font-semibold text-gray-800">{{ __('ui.purchasing.items') }}</h2>
            <div class="mb-6 overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.purchasing.product') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.purchasing.qty') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.purchasing.unit_cost_actual') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.purchasing.line_total') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($receipt->items as $item)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 font-medium text-gray-800">{{ $item->purchaseOrderItem->product->name }}</td>
                                <td class="px-4 py-3 tnum">{{ $item->qty }}</td>
                                <td class="px-4 py-3 tnum">{{ \App\Support\Money::fromDecimal((string) $item->unit_cost_actual)->format() }}</td>
                                <td class="px-4 py-3 tnum">{{ \App\Support\Money::fromDecimal((string) $item->unit_cost_actual)->mul($item->qty)->format() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <h2 class="mb-2 text-base font-semibold text-gray-800">{{ __('ui.purchasing.payments') }}</h2>
            <div class="mb-6 overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.purchasing.number') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.purchasing.payment_date') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.purchasing.method') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.purchasing.amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($receipt->allocations as $allocation)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 font-medium text-gray-800">{{ $allocation->payment->number }}</td>
                                <td class="px-4 py-3">{{ $allocation->payment->payment_date->format('d/m/Y') }}</td>
                                <td class="px-4 py-3">{{ __('ui.purchasing.methods.'.$allocation->payment->method) }}</td>
                                <td class="px-4 py-3 tnum">{{ \App\Support\Money::fromDecimal((string) $allocation->amount)->format() }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4"><x-lentera.empty-state>{{ __('ui.purchasing.empty_payments') }}</x-lentera.empty-state></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($receipt->returns->isNotEmpty())
                <h2 class="mb-2 text-base font-semibold text-gray-800">{{ __('ui.purchasing.returns') }}</h2>
                <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 text-left text-gray-500">
                                <th class="px-4 py-3 font-medium">{{ __('ui.purchasing.number') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('ui.purchasing.return_reason') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('ui.purchasing.credit_status') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('ui.purchasing.total') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($receipt->returns as $retur)
                                <tr class="border-b border-gray-50 last:border-0">
                                    <td class="px-4 py-3 font-medium text-gray-800">{{ $retur->number }}</td>
                                    <td class="px-4 py-3">{{ $retur->reason }}</td>
                                    <td class="px-4 py-3">{{ $retur->credit_status === 'reduces_payable' ? __('ui.purchasing.credit_reduces') : __('ui.purchasing.credit_supplier') }}</td>
                                    <td class="px-4 py-3 tnum">{{ \App\Support\Money::fromDecimal((string) $retur->total_amount)->format() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
