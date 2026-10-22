<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.incentives.detail') }} — {{ $period->period_start->format('F Y') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-6xl space-y-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <p class="rounded-md bg-lentera-100 px-4 py-2 text-sm text-lentera-900" role="status">{{ session('status') }}</p>
            @endif
            @if ($errors->any())
                <ul class="rounded-md bg-red-50 px-4 py-2 text-sm text-red-700">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            @endif
            <div class="flex items-center justify-between">
                <p><a href="{{ route('incentives.index') }}" class="text-sm text-lentera-800 underline">{{ __('ui.incentives.back') }}</a></p>
                <x-lentera.aksi-bar>
                    @if ($period->isDraft())
                        <form method="POST" action="{{ route('incentives.approve', $period) }}">
                            @csrf
                            <button type="submit" class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-sm font-medium text-gray-700 shadow-soft transition duration-150 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-lentera-500">{{ __('ui.incentives.approve') }}</button>
                        </form>
                    @endif
                    @if ($period->status === 'approved')
                        <form method="POST" action="{{ route('incentives.pay', $period) }}">
                            @csrf
                            <button type="submit" class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-sm font-medium text-gray-700 shadow-soft transition duration-150 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-lentera-500">{{ __('ui.incentives.pay') }}</button>
                        </form>
                    @endif
                    <x-lentera.menu-item :href="route('incentives.export', $period)">{{ __('ui.incentives.export') }}</x-lentera.menu-item>
                </x-lentera.aksi-bar>
            </div>

            <section class="rounded-lg bg-white p-6 shadow-soft">
                <dl class="grid gap-3 text-sm sm:grid-cols-2">
                    <div><dt class="text-gray-500">{{ __('ui.incentives.period') }}</dt><dd class="font-medium">{{ $period->period_start->format('d/m/Y') }} – {{ $period->period_end->format('d/m/Y') }}</dd></div>
                    <div><dt class="text-gray-500">{{ __('ui.incentives.branch') }}</dt><dd class="font-medium">{{ $period->branch?->name ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">{{ __('ui.incentives.scope') }}</dt><dd class="font-medium">{{ $period->recipient?->name ?? __('ui.incentives.recipient_all') }}</dd></div>
                    <div><dt class="text-gray-500">{{ __('ui.incentives.status') }}</dt><dd class="font-medium">{{ __('ui.incentives.status_'.$period->status) }}</dd></div>
                    <div><dt class="text-gray-500">{{ __('ui.incentives.transaction_total') }}</dt><dd class="font-medium tnum">{{ \App\Support\Money::fromDecimal((string) $period->transaction_total)->format() }}</dd></div>
                    <div><dt class="text-gray-500">{{ __('ui.incentives.total') }}</dt><dd class="font-medium tnum">{{ \App\Support\Money::fromDecimal((string) $period->incentive_total)->format() }}</dd></div>
                    <div><dt class="text-gray-500">{{ __('ui.incentives.adjustments_total') }}</dt><dd class="font-medium tnum">{{ $period->adjustmentsTotal()->format() }}</dd></div>
                    <div><dt class="text-gray-500">{{ __('ui.incentives.net_total') }}</dt><dd class="font-semibold tnum">{{ $period->netTotal()->format() }}</dd></div>
                    @if ($period->approver)
                        <div><dt class="text-gray-500">{{ __('ui.incentives.approved_by') }}</dt><dd class="font-medium">{{ $period->approver->name }} · {{ $period->approved_at?->format('d/m/Y H:i') }}</dd></div>
                    @endif
                    @if ($period->payer)
                        <div><dt class="text-gray-500">{{ __('ui.incentives.paid_by') }}</dt><dd class="font-medium">{{ $period->payer->name }} · {{ $period->paid_at?->format('d/m/Y H:i') }}</dd></div>
                    @endif
                </dl>
            </section>

            <section class="overflow-hidden rounded-lg bg-white shadow-soft">
                <h2 class="border-b border-gray-100 px-4 py-3 font-semibold text-gray-800">{{ __('ui.incentives.items') }}</h2>
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.incentives.recipient') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.incentives.sale') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.incentives.product') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ __('ui.incentives.qty') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ __('ui.incentives.basis') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ __('ui.incentives.amount') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.incentives.item_status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($period->items as $item)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3">{{ $item->recipient?->name }} <span class="text-xs text-gray-400">({{ $item->recipient_role }})</span></td>
                                <td class="px-4 py-3">{{ $item->sale?->number }}</td>
                                <td class="px-4 py-3">{{ $item->product_name ?? '—' }}</td>
                                <td class="px-4 py-3 text-right tnum">{{ $item->quantity }}</td>
                                <td class="px-4 py-3 text-right tnum">{{ \App\Support\Money::fromDecimal((string) $item->item_final_amount)->format() }}</td>
                                <td class="px-4 py-3 text-right tnum">{{ \App\Support\Money::fromDecimal((string) $item->incentive_amount)->format() }}</td>
                                <td class="px-4 py-3">
                                    @if ($item->status === 'eligible')
                                        <span class="inline-flex rounded-full bg-lentera-50 px-2 py-0.5 text-xs font-medium text-lentera-800 ring-1 ring-lentera-200">{{ __('ui.incentives.item_eligible') }}</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700 ring-1 ring-red-200">{{ __('ui.incentives.item_cancelled') }}</span>
                                        @if ($item->cancellation_reason)<span class="mt-1 block text-xs text-gray-500">{{ $item->cancellation_reason }}</span>@endif
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><x-lentera.empty-state>{{ __('ui.incentives.items_empty') }}</x-lentera.empty-state></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </section>

            @if ($period->adjustments->isNotEmpty())
                <section class="overflow-hidden rounded-lg bg-white shadow-soft">
                    <h2 class="border-b border-gray-100 px-4 py-3 font-semibold text-gray-800">{{ __('ui.incentives.adjustments') }}</h2>
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 text-left text-gray-500">
                                <th class="px-4 py-3 font-medium">{{ __('ui.incentives.recipient') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('ui.incentives.product') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('ui.incentives.reason') }}</th>
                                <th class="px-4 py-3 text-right font-medium">{{ __('ui.incentives.amount') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($period->adjustments as $adjustment)
                                <tr class="border-b border-gray-50 last:border-0">
                                    <td class="px-4 py-3">{{ $adjustment->recipient?->name }} <span class="text-xs text-gray-400">({{ $adjustment->recipient_role }})</span></td>
                                    <td class="px-4 py-3">{{ $adjustment->product_name ?? '—' }} <span class="text-xs text-gray-400">· {{ $adjustment->returned_quantity }} qty</span></td>
                                    <td class="px-4 py-3">{{ $adjustment->reason }}</td>
                                    <td class="px-4 py-3 text-right font-semibold text-red-700 tnum">{{ \App\Support\Money::fromDecimal((string) $adjustment->amount)->format() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </section>
            @endif
        </div>
    </div>
</x-app-layout>
