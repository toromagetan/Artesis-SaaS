<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.sales.create_schedule') }} — {{ $sale->number }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
            @if ($errors->any())
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif
            <div class="rounded-lg bg-white p-6 shadow-soft">
                <p class="mb-4 text-sm text-gray-600">{{ $sale->customer->name }} · {{ __('ui.sales.total') }}: <span class="tnum font-medium">{{ \App\Support\Money::fromDecimal((string) $sale->total_amount)->format() }}</span> · {{ __('ui.sales.remaining') }}: <span class="tnum font-medium">{{ $sale->remaining()->format() }}</span></p>
                <p class="mb-4 text-sm text-gray-500">{{ __('ui.sales.schedule_hint') }}</p>
                <form method="POST" action="{{ route('sales.installment.store', $sale) }}">
                    @csrf
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <x-input-label for="installment_count" :value="__('ui.sales.installment_count')" />
                            <x-text-input id="installment_count" name="installment_count" type="number" min="1" max="36" class="mt-1 block w-full tnum" :value="old('installment_count', 3)" required />
                        </div>
                        <div>
                            <x-input-label for="installment_first_due" :value="__('ui.sales.first_due_date')" />
                            <x-text-input id="installment_first_due" name="installment_first_due" type="date" class="mt-1 block w-full" :value="old('installment_first_due', now()->addDays(30)->toDateString())" required />
                        </div>
                        <div>
                            <x-input-label for="installment_interval_days" :value="__('ui.sales.interval_days')" />
                            <x-text-input id="installment_interval_days" name="installment_interval_days" type="number" min="1" max="365" class="mt-1 block w-full tnum" :value="old('installment_interval_days', 30)" required />
                        </div>
                    </div>
                    <div class="mt-6 flex items-center gap-3">
                        <a href="{{ route('sales.show', $sale) }}" class="text-sm text-gray-600 underline">{{ __('ui.back') }}</a>
                        <x-primary-button>{{ __('ui.save') }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
