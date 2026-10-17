<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.purchasing.pay') }} — {{ $receipt->number }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
            @if ($errors->any())
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif
            <div class="rounded-lg bg-white p-6 shadow-soft">
                <p class="mb-4 text-sm text-gray-600">{{ $receipt->purchaseOrder->supplier->name }} · {{ __('ui.purchasing.total') }}: <span class="tnum font-medium">{{ $receipt->totalAmount()->format() }}</span> · {{ __('ui.purchasing.remaining') }}: <span class="tnum font-medium">{{ $receipt->remainingAmount()->format() }}</span></p>
                <form method="POST" action="{{ route('receipts.pay.store', $receipt) }}">
                    @csrf
                    <div class="grid gap-4">
                        <div>
                            <x-input-label for="amount" :value="__('ui.purchasing.amount')" />
                            <x-text-input id="amount" name="amount" type="number" step="0.01" min="0" class="mt-1 block w-full tnum" :value="old('amount', $receipt->remainingAmount()->toDecimalString())" required />
                        </div>
                        <div>
                            <x-input-label for="method" :value="__('ui.purchasing.method')" />
                            <select id="method" name="method" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                                @foreach (\App\Modules\Purchasing\Models\PurchasePayment::METHODS as $method)
                                    <option value="{{ $method }}" @selected(old('method') === $method)>{{ __('ui.purchasing.methods.'.$method) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="payment_date" :value="__('ui.purchasing.payment_date')" />
                                <x-text-input id="payment_date" name="payment_date" type="date" class="mt-1 block w-full" :value="old('payment_date', now()->toDateString())" />
                            </div>
                            <div>
                                <x-input-label for="reference" :value="__('ui.purchasing.reference')" />
                                <x-text-input id="reference" name="reference" class="mt-1 block w-full" :value="old('reference')" maxlength="80" />
                            </div>
                        </div>
                    </div>
                    <div class="mt-6 flex items-center gap-3">
                        <a href="{{ route('receipts.show', $receipt) }}" class="text-sm text-gray-600 underline">{{ __('ui.back') }}</a>
                        <x-primary-button>{{ __('ui.save') }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
