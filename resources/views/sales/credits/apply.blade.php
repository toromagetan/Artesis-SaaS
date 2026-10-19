<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.credits.apply_title') }} — {{ $credit->credit_number }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
            @if ($errors->any())
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif
            <div class="rounded-lg bg-white p-6 shadow-soft">
                <p class="mb-4 text-sm text-gray-600">
                    {{ $credit->customer->name }} ·
                    {{ __('ui.credits.amount') }}: <span class="tnum font-medium">{{ \App\Support\Money::fromDecimal((string) $credit->amount)->format() }}</span> ·
                    {{ __('ui.credits.remaining') }}: <span class="tnum font-medium">{{ \App\Support\Money::fromDecimal((string) $credit->remaining_amount)->format() }}</span>
                </p>
                @if ($sales->isEmpty())
                    <x-lentera.empty-state>{{ __('ui.credits.no_open_sales') }}</x-lentera.empty-state>
                    <div class="mt-6">
                        <a href="{{ route('customers.show', $credit->customer) }}" class="text-sm text-gray-600 underline">{{ __('ui.back') }}</a>
                    </div>
                @else
                    <form method="POST" action="{{ route('customer-credits.apply.store', $credit) }}">
                        @csrf
                        <div class="grid gap-4">
                            <div>
                                <x-input-label for="sale_id" :value="__('ui.credits.sale')" />
                                <select id="sale_id" name="sale_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500" required>
                                    @foreach ($sales as $sale)
                                        <option value="{{ $sale->id }}" @selected(old('sale_id') == $sale->id)>{{ $sale->number }} — {{ $sale->sale_date->format('d/m/Y') }} · {{ __('ui.sales.remaining') }}: {{ $sale->remaining()->format() }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('sale_id')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="amount" :value="__('ui.sales.amount')" />
                                <x-text-input id="amount" name="amount" type="number" step="0.01" min="0" class="mt-1 block w-full tnum" :value="old('amount', $credit->remaining_amount)" required />
                                <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                            </div>
                        </div>
                        <div class="mt-6 flex items-center gap-3">
                            <a href="{{ route('customers.show', $credit->customer) }}" class="text-sm text-gray-600 underline">{{ __('ui.back') }}</a>
                            <x-primary-button>{{ __('ui.credits.apply') }}</x-primary-button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
