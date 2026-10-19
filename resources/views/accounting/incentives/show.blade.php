<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.incentives.detail') }} — {{ $period->recipient->name }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl space-y-4 sm:px-6 lg:px-8">
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
                @if ($period->isDraft())
                    <x-lentera.dropdown-aksi>
                        <form method="POST" action="{{ route('incentives.approve', $period) }}">
                            @csrf
                            <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100">{{ __('ui.incentives.approve') }}</button>
                        </form>
                    </x-lentera.dropdown-aksi>
                @endif
            </div>

            <section class="rounded-lg bg-white p-6 shadow-soft">
                <dl class="grid gap-3 text-sm sm:grid-cols-2">
                    <div><dt class="text-gray-500">{{ __('ui.incentives.period') }}</dt><dd class="font-medium">{{ $period->period_start->format('d/m/Y') }} – {{ $period->period_end->format('d/m/Y') }}</dd></div>
                    <div><dt class="text-gray-500">{{ __('ui.incentives.branch') }}</dt><dd class="font-medium">{{ $period->branch->name }}</dd></div>
                    <div><dt class="text-gray-500">{{ __('ui.incentives.recipient') }}</dt><dd class="font-medium">{{ $period->recipient->name }}</dd></div>
                    <div><dt class="text-gray-500">{{ __('ui.incentives.status') }}</dt><dd class="font-medium">{{ __('ui.incentives.status_'.$period->status) }}</dd></div>
                    <div><dt class="text-gray-500">{{ __('ui.incentives.transaction_total') }}</dt><dd class="font-medium tnum">{{ \App\Support\Money::fromDecimal((string) $period->transaction_total)->format() }}</dd></div>
                    <div><dt class="text-gray-500">{{ __('ui.incentives.total') }}</dt><dd class="font-medium tnum">{{ \App\Support\Money::fromDecimal((string) $period->incentive_total)->format() }}</dd></div>
                    @if ($period->approver)
                        <div><dt class="text-gray-500">{{ __('ui.incentives.approved_by') }}</dt><dd class="font-medium">{{ $period->approver->name }} · {{ $period->approved_at?->format('d/m/Y H:i') }}</dd></div>
                    @endif
                    @if ($period->payer)
                        <div><dt class="text-gray-500">{{ __('ui.incentives.paid_by') }}</dt><dd class="font-medium">{{ $period->payer->name }} · {{ $period->paid_at?->format('d/m/Y H:i') }} · {{ __('ui.sales.methods.'.$period->pay_method) }}</dd></div>
                    @endif
                </dl>
            </section>

            <section class="overflow-hidden rounded-lg bg-white shadow-soft">
                <h2 class="border-b border-gray-100 px-4 py-3 font-semibold text-gray-800">{{ __('ui.incentives.items') }}</h2>
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.incentives.rule_name') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.incentives.product') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ __('ui.incentives.basis') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ __('ui.incentives.amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($period->items as $item)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3">{{ $item->rule->name }}</td>
                                <td class="px-4 py-3">{{ $item->product?->sku ?? __('ui.incentives.product_all') }}</td>
                                <td class="px-4 py-3 text-right tnum">{{ \App\Support\Money::fromDecimal((string) $item->basis_amount)->format() }}@if ($item->basis_qty) · {{ $item->basis_qty }} @endif</td>
                                <td class="px-4 py-3 text-right tnum">{{ \App\Support\Money::fromDecimal((string) $item->amount)->format() }}</td>
                            </tr>
                        @endforeach
                        @foreach ($period->adjustments as $adjustment)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3">{{ __('ui.incentives.adjustment') }} — {{ $adjustment->reason }} <span class="text-xs text-gray-400">({{ $adjustment->creator->name }})</span></td>
                                <td class="px-4 py-3">—</td>
                                <td class="px-4 py-3 text-right">—</td>
                                <td class="px-4 py-3 text-right tnum">{{ \App\Support\Money::fromDecimal((string) $adjustment->amount)->format() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>

            @if ($period->isDraft())
                <section class="rounded-lg bg-white p-6 shadow-soft">
                    <h2 class="mb-3 font-semibold text-gray-800">{{ __('ui.incentives.add_adjustment') }}</h2>
                    <form method="POST" action="{{ route('incentives.adjust', $period) }}" class="flex flex-wrap items-end gap-3">
                        @csrf
                        <label class="text-sm">
                            <span class="mb-1 block text-gray-500">{{ __('ui.incentives.amount') }}</span>
                            <input type="number" name="amount" step="0.01" required class="rounded-md border-gray-300 text-sm">
                        </label>
                        <label class="text-sm">
                            <span class="mb-1 block text-gray-500">{{ __('ui.incentives.reason') }}</span>
                            <input type="text" name="reason" maxlength="200" required class="rounded-md border-gray-300 text-sm">
                        </label>
                        <button type="submit" class="rounded-md bg-lentera-700 px-4 py-2 text-sm font-medium text-white transition duration-150 hover:bg-lentera-800">{{ __('ui.incentives.add') }}</button>
                    </form>
                </section>
            @endif

            @if ($period->status === 'approved')
                <section class="rounded-lg bg-white p-6 shadow-soft">
                    <h2 class="mb-3 font-semibold text-gray-800">{{ __('ui.incentives.pay_title') }}</h2>
                    <form method="POST" action="{{ route('incentives.pay', $period) }}" class="flex flex-wrap items-end gap-3">
                        @csrf
                        <label class="text-sm">
                            <span class="mb-1 block text-gray-500">{{ __('ui.sales.method') }}</span>
                            <select name="method" class="rounded-md border-gray-300 text-sm">
                                @foreach (\App\Modules\Accounting\Services\IncentiveService::PAY_METHODS as $method)
                                    <option value="{{ $method }}">{{ __('ui.sales.methods.'.$method) }}</option>
                                @endforeach
                            </select>
                        </label>
                        <button type="submit" class="rounded-md bg-lentera-700 px-4 py-2 text-sm font-medium text-white transition duration-150 hover:bg-lentera-800">{{ __('ui.incentives.pay') }}</button>
                    </form>
                </section>
            @endif
        </div>
    </div>
</x-app-layout>
