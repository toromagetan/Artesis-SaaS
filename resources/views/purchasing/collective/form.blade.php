<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.purchasing.collective_title') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
            @if ($errors->any())
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            <form method="GET" class="mb-6 flex items-end gap-3 rounded-lg bg-white p-4 shadow-soft">
                <div>
                    <x-input-label for="supplier" :value="__('ui.purchasing.supplier')" />
                    <select id="supplier" name="supplier" class="mt-1 block w-72 rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                        <option value="">—</option>
                        @foreach ($suppliers as $s)
                            <option value="{{ $s->id }}" @selected($supplier?->id === $s->id)>{{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>
                <x-secondary-button type="submit">{{ __('ui.search') }}</x-secondary-button>
            </form>

            @if ($supplier)
                <h2 class="mb-2 text-base font-semibold text-gray-800">{{ __('ui.purchasing.outstanding') }} — {{ $supplier->name }}</h2>
                <div class="mb-6 overflow-hidden rounded-lg bg-white shadow-soft">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 text-left text-gray-500">
                                <th class="px-4 py-3 font-medium">{{ __('ui.purchasing.receipt_number') }}</th>
                                <th class="px-4 py-3 font-medium">PO</th>
                                <th class="px-4 py-3 font-medium">{{ __('ui.purchasing.received_date') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('ui.purchasing.total') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('ui.purchasing.remaining') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($outstanding as $receipt)
                                <tr class="border-b border-gray-50 last:border-0">
                                    <td class="px-4 py-3 font-medium text-gray-800">{{ $receipt->number }}</td>
                                    <td class="px-4 py-3">{{ $receipt->purchaseOrder->number }}</td>
                                    <td class="px-4 py-3">{{ $receipt->received_date->format('d/m/Y') }}</td>
                                    <td class="px-4 py-3 tnum">{{ $receipt->totalAmount()->format() }}</td>
                                    <td class="px-4 py-3 tnum">{{ $receipt->remainingAmount()->format() }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5"><x-lentera.empty-state>{{ __('ui.purchasing.empty_outstanding') }}</x-lentera.empty-state></td></tr>
                            @endforelse
                        </tbody>
                        @if ($totalOutstanding)
                            <tfoot>
                                <tr class="text-sm font-semibold">
                                    <td colspan="4" class="px-4 py-2 text-right">{{ __('ui.purchasing.total_outstanding') }}</td>
                                    <td class="px-4 py-2 tnum">{{ $totalOutstanding->format() }}</td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>

                @if (count($outstanding) > 0)
                    <div class="rounded-lg bg-white p-6 shadow-soft">
                        <form method="POST" action="{{ route('payments.collective.process') }}">
                            @csrf
                            <input type="hidden" name="supplier_id" value="{{ $supplier->id }}">
                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <x-input-label for="amount" :value="__('ui.purchasing.amount')" />
                                    <x-text-input id="amount" name="amount" type="number" step="0.01" min="0" class="mt-1 block w-full tnum" :value="old('amount')" required />
                                </div>
                                <div>
                                    <x-input-label for="method" :value="__('ui.purchasing.method')" />
                                    <select id="method" name="method" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                                        @foreach (\App\Modules\Purchasing\Models\PurchasePayment::METHODS as $method)
                                            <option value="{{ $method }}" @selected(old('method') === $method)>{{ __('ui.purchasing.methods.'.$method) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <x-input-label for="payment_date" :value="__('ui.purchasing.payment_date')" />
                                    <x-text-input id="payment_date" name="payment_date" type="date" class="mt-1 block w-full" :value="old('payment_date', now()->toDateString())" />
                                </div>
                                <div>
                                    <x-input-label for="reference" :value="__('ui.purchasing.reference')" />
                                    <x-text-input id="reference" name="reference" class="mt-1 block w-full" :value="old('reference')" maxlength="80" />
                                </div>
                            </div>
                            <div class="mt-6">
                                <x-primary-button>{{ __('ui.purchasing.collective') }}</x-primary-button>
                            </div>
                        </form>
                    </div>
                @endif
            @endif
        </div>
    </div>
</x-app-layout>
