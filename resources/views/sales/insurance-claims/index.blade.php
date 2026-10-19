<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.insurance_claims.title') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <section class="overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.insurance_claims.sale') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.insurance_claims.insurance') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.insurance_claims.customer') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.insurance_claims.branch') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ __('ui.insurance_claims.claim') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ __('ui.insurance_claims.settled') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ __('ui.insurance_claims.remaining') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.insurance_claims.status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($usages as $usage)
                            <tr class="border-b border-gray-50 last:border-0 hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <a href="{{ route('insurance-claims.show', $usage) }}" class="font-medium text-lentera-800 underline">{{ $usage->sale->number }}</a>
                                    <span class="block text-xs text-gray-400">{{ $usage->used_at->format('d/m/Y') }}</span>
                                </td>
                                <td class="px-4 py-3">{{ $usage->insurance->name }}</td>
                                <td class="px-4 py-3">{{ $usage->customer->name }}</td>
                                <td class="px-4 py-3">{{ $usage->branch->name }}</td>
                                <td class="px-4 py-3 text-right tnum">{{ \App\Support\Money::fromDecimal((string) $usage->insurance_claim_amount)->format() }}</td>
                                <td class="px-4 py-3 text-right tnum">{{ \App\Support\Money::fromDecimal($usage->settledAmount())->format() }}</td>
                                <td class="px-4 py-3 text-right font-medium tnum">{{ \App\Support\Money::fromDecimal($usage->remainingAmount())->format() }}</td>
                                <td class="px-4 py-3">
                                    @if ($usage->remainingAmount() === '0.00')
                                        <span class="inline-flex rounded-full bg-lentera-100 px-2.5 py-0.5 text-xs font-medium text-lentera-800">{{ __('ui.insurance_claims.settled_status') }}</span>
                                    @else
                                        <span class="inline-flex rounded-full border border-gold/50 bg-gold/10 px-2.5 py-0.5 text-xs font-medium text-gold-dark">{{ __('ui.insurance_claims.open_status') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8"><x-lentera.empty-state>{{ __('ui.insurance_claims.empty') }}</x-lentera.empty-state></td></tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="border-t border-gray-50 px-4 py-3">{{ $usages->links() }}</div>
            </section>
        </div>
    </div>
</x-app-layout>
