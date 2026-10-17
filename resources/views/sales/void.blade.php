<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.sales.void_title') }} — {{ $sale->number }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
            @if ($errors->any())
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif
            <div class="rounded-lg bg-white p-6 shadow-soft">
                <p class="mb-4 text-sm text-gray-600">{{ $sale->customer->name }} · {{ __('ui.sales.total') }}: <span class="tnum font-medium">{{ \App\Support\Money::fromDecimal((string) $sale->total_amount)->format() }}</span>. Void membalik seluruh pembayaran transaksi ini dan tidak dapat dibatalkan.</p>
                <form method="POST" action="{{ route('sales.void.store', $sale) }}">
                    @csrf
                    <div>
                        <x-input-label for="reason" :value="__('ui.sales.void_reason')" />
                        <x-text-input id="reason" name="reason" class="mt-1 block w-full" :value="old('reason')" required maxlength="255" />
                        <x-input-error :messages="$errors->get('reason')" class="mt-2" />
                    </div>
                    <div class="mt-6 flex items-center gap-3">
                        <a href="{{ route('sales.show', $sale) }}" class="text-sm text-gray-600 underline">{{ __('ui.back') }}</a>
                        <x-danger-button>{{ __('ui.sales.void') }}</x-danger-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
