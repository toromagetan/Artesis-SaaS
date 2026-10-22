<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ $payment->number }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-lg border border-lentera-200 bg-lentera-50 px-4 py-3 text-sm text-lentera-800">{{ session('status') }}</div>
            @endif

            <div class="mb-4 flex justify-end">
                <x-lentera.aksi-bar>
                    <x-lentera.menu-item :href="route('payments.print', $payment)" target="_blank" print>{{ __('ui.print') }}</x-lentera.menu-item>
                </x-lentera.aksi-bar>
            </div>

            <div class="mb-6 rounded-lg bg-white p-6 shadow-soft">
                <dl class="grid grid-cols-[160px_1fr] gap-y-3 text-sm">
                    <dt class="text-gray-500">{{ __('ui.purchasing.supplier') }}</dt>
                    <dd class="font-medium text-gray-800">{{ $payment->supplier->name }}</dd>
                    <dt class="text-gray-500">{{ __('ui.purchasing.payment_date') }}</dt>
                    <dd class="font-medium text-gray-800">{{ $payment->payment_date->format('d/m/Y') }}</dd>
                    <dt class="text-gray-500">{{ __('ui.purchasing.method') }}</dt>
                    <dd class="font-medium text-gray-800">{{ __('ui.purchasing.methods.'.$payment->method) }}</dd>
                    <dt class="text-gray-500">{{ __('ui.purchasing.amount') }}</dt>
                    <dd class="font-medium text-gray-800 tnum">{{ \App\Support\Money::fromDecimal((string) $payment->amount)->format() }}</dd>
                    @if ($payment->reference)
                        <dt class="text-gray-500">{{ __('ui.purchasing.reference') }}</dt>
                        <dd class="font-medium text-gray-800">{{ $payment->reference }}</dd>
                    @endif
                </dl>
            </div>

            <h2 class="mb-2 text-base font-semibold text-gray-800">{{ __('ui.purchasing.allocations') }}</h2>
            <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.purchasing.receipt_number') }}</th>
                            <th class="px-4 py-3 font-medium">PO</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.purchasing.received_date') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.purchasing.amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($payment->allocations as $allocation)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 font-medium"><a href="{{ route('receipts.show', $allocation->receipt) }}" class="text-lentera-800 underline-offset-2 hover:underline">{{ $allocation->receipt->number }}</a></td>
                                <td class="px-4 py-3">{{ $allocation->receipt->purchaseOrder->number }}</td>
                                <td class="px-4 py-3">{{ $allocation->receipt->received_date->format('d/m/Y') }}</td>
                                <td class="px-4 py-3 tnum">{{ \App\Support\Money::fromDecimal((string) $allocation->amount)->format() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
