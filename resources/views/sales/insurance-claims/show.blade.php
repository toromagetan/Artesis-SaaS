<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.insurance_claims.detail') }} — {{ $usage->sale->number }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl space-y-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <p class="rounded-md bg-lentera-100 px-4 py-2 text-sm text-lentera-900" role="status">{{ session('status') }}</p>
            @endif
            <p><a href="{{ route('insurance-claims.index') }}" class="text-sm text-lentera-800 underline">{{ __('ui.insurance_claims.back') }}</a></p>

            <section class="rounded-lg bg-white p-6 shadow-soft">
                <dl class="grid gap-3 text-sm sm:grid-cols-2">
                    <div><dt class="text-gray-500">{{ __('ui.insurance_claims.sale') }}</dt><dd class="font-medium"><a href="{{ route('sales.show', $usage->sale) }}" class="text-lentera-800 underline">{{ $usage->sale->number }}</a></dd></div>
                    <div><dt class="text-gray-500">{{ __('ui.insurance_claims.insurance') }}</dt><dd class="font-medium">{{ $usage->insurance->name }}</dd></div>
                    <div><dt class="text-gray-500">{{ __('ui.insurance_claims.customer') }}</dt><dd class="font-medium">{{ $usage->customer->name }}</dd></div>
                    <div><dt class="text-gray-500">{{ __('ui.insurance_claims.branch') }}</dt><dd class="font-medium">{{ $usage->branch->name }}</dd></div>
                    <div><dt class="text-gray-500">{{ __('ui.insurance_claims.transaction') }}</dt><dd class="font-medium tnum">{{ \App\Support\Money::fromDecimal((string) $usage->transaction_amount)->format() }}</dd></div>
                    <div><dt class="text-gray-500">{{ __('ui.insurance_claims.claim') }}</dt><dd class="font-medium tnum">{{ \App\Support\Money::fromDecimal((string) $usage->insurance_claim_amount)->format() }}</dd></div>
                    <div><dt class="text-gray-500">{{ __('ui.insurance_claims.settled') }}</dt><dd class="font-medium tnum">{{ \App\Support\Money::fromDecimal($usage->settledAmount())->format() }}</dd></div>
                    <div><dt class="text-gray-500">{{ __('ui.insurance_claims.remaining') }}</dt><dd class="font-medium tnum">{{ \App\Support\Money::fromDecimal($usage->remainingAmount())->format() }}</dd></div>
                </dl>
            </section>

            <section class="overflow-hidden rounded-lg bg-white shadow-soft">
                <h2 class="border-b border-gray-100 px-4 py-3 font-semibold text-gray-800">{{ __('ui.insurance_claims.receipts') }}</h2>
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.insurance_claims.received_at') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.sales.method') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.insurance_claims.received_by') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ __('ui.insurance_claims.amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($usage->receipts as $receipt)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 tnum">{{ $receipt->received_at->format('d/m/Y H:i') }}</td>
                                <td class="px-4 py-3">{{ __('ui.sales.methods.'.$receipt->method) }}</td>
                                <td class="px-4 py-3">{{ $receipt->receiver->name }}</td>
                                <td class="px-4 py-3 text-right tnum">{{ \App\Support\Money::fromDecimal((string) $receipt->amount)->format() }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4"><x-lentera.empty-state>{{ __('ui.insurance_claims.no_receipts') }}</x-lentera.empty-state></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </section>

            @can('sales.pay')
                @if ($usage->remainingAmount() !== '0.00')
                    <section class="rounded-lg bg-white p-6 shadow-soft">
                        <h2 class="mb-3 font-semibold text-gray-800">{{ __('ui.insurance_claims.receive_title') }}</h2>
                        <form method="POST" action="{{ route('insurance-claims.receive', $usage) }}" class="flex flex-wrap items-end gap-3">
                            @csrf
                            <label class="text-sm">
                                <span class="mb-1 block text-gray-500">{{ __('ui.insurance_claims.amount') }}</span>
                                <input type="number" name="amount" min="0" step="0.01" required class="rounded-md border-gray-300 text-sm">
                            </label>
                            <label class="text-sm">
                                <span class="mb-1 block text-gray-500">{{ __('ui.sales.method') }}</span>
                                <select name="method" class="rounded-md border-gray-300 text-sm">
                                    @foreach (\App\Modules\Sales\Services\InsuranceClaimService::METHODS as $method)
                                        <option value="{{ $method }}">{{ __('ui.sales.methods.'.$method) }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <button type="submit" class="rounded-md bg-lentera-700 px-4 py-2 text-sm font-medium text-white transition duration-150 hover:bg-lentera-800">{{ __('ui.insurance_claims.receive') }}</button>
                        </form>
                        @if ($errors->any())
                            <ul class="mt-3 text-sm text-red-600">
                                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                            </ul>
                        @endif
                    </section>
                @endif
            @endcan
        </div>
    </div>
</x-app-layout>
