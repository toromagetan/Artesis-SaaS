<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.payroll.commissions_title') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <form method="GET" action="{{ route('commissions.index') }}" class="flex items-end gap-2">
                    <div>
                        <x-input-label for="periode" :value="__('ui.periods.period')" />
                        <x-text-input id="periode" name="periode" type="month" value="{{ $period }}" />
                    </div>
                    <x-secondary-button type="submit">{{ __('ui.reports.apply') }}</x-secondary-button>
                </form>
                <x-lentera.aksi-bar>
                    <form method="POST" action="{{ route('commissions.post') }}">
                        @csrf
                        <input type="hidden" name="period" value="{{ $period }}">
                        <button type="submit" class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-sm font-medium text-gray-700 shadow-soft transition duration-150 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-lentera-500">{{ __('ui.payroll.post_period') }}</button>
                    </form>
                    <x-lentera.menu-item :href="route('payroll.index')">{{ __('ui.payroll.payroll_link') }}</x-lentera.menu-item>
                </x-lentera.aksi-bar>
            </div>

            @if ($payables->isNotEmpty())
                <div class="mb-6 rounded-lg bg-white p-6 shadow-soft">
                    <h2 class="text-base font-semibold text-lentera-950">{{ __('ui.payroll.payable_now') }}</h2>
                    <ul class="mt-3 divide-y divide-gray-50">
                        @foreach ($payables as $payable)
                            <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                                <span class="text-sm">
                                    <span class="font-medium">{{ $payable['name'] }}</span>
                                    <span class="text-gray-400">· {{ __('ui.payroll.type_'.$payable['type']) }}</span>
                                    — <span class="tnum">{{ \App\Support\Money::of($payable['net_cents'])->format() }}</span>
                                </span>
                                <form method="POST" action="{{ route('commissions.pay') }}" class="flex items-center gap-2">
                                    @csrf
                                    <input type="hidden" name="type" value="{{ $payable['type'] }}">
                                    @if ($payable['doctor_id'])<input type="hidden" name="doctor_id" value="{{ $payable['doctor_id'] }}">@endif
                                    @if ($payable['user_id'])<input type="hidden" name="user_id" value="{{ $payable['user_id'] }}">@endif
                                    <select name="method" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                                        <option value="cash">{{ __('ui.payroll.cash') }}</option>
                                        <option value="transfer">{{ __('ui.payroll.transfer') }}</option>
                                    </select>
                                    <x-primary-button>{{ __('ui.payroll.pay_now') }}</x-primary-button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.payroll.beneficiary') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.payroll.source') }}</th>
                            <th class="px-4 py-3 font-medium text-right">{{ __('ui.payroll.commission_base') }}</th>
                            <th class="px-4 py-3 font-medium text-right">{{ __('ui.payroll.rate') }}</th>
                            <th class="px-4 py-3 font-medium text-right">{{ __('ui.payroll.amount') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.payroll.status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 font-medium">
                                    {{ $row->type === 'doctor' ? $row->doctor?->name : $row->beneficiaryUser?->name }}
                                    <span class="block text-xs font-normal text-gray-400">{{ __('ui.payroll.type_'.$row->type) }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('sales.show', $row->source_sale_id) }}" class="text-lentera-800 underline">{{ $row->sale?->number }}</a>
                                    @if ($row->corrects_id)<span class="block text-xs text-gray-400">{{ __('ui.payroll.correction_of') }}</span>@endif
                                </td>
                                <td class="px-4 py-3 text-right tnum">{{ \App\Support\Money::fromDecimal((string) $row->base_amount)->format() }}</td>
                                <td class="px-4 py-3 text-right tnum">{{ $row->rate }}%</td>
                                <td class="px-4 py-3 text-right font-medium tnum">{{ $row->amountMoney()->format() }}</td>
                                <td class="px-4 py-3">
                                    @if ($row->status === 'paid')
                                        <span class="inline-flex items-center rounded-full border border-lentera-200 bg-lentera-50 px-2 py-0.5 text-xs font-semibold text-lentera-700">{{ __('ui.payroll.status_paid') }}</span>
                                    @elseif ($row->status === 'corrected')
                                        <span class="inline-flex items-center rounded-full border border-red-200 bg-red-50 px-2 py-0.5 text-xs font-semibold text-red-600">{{ __('ui.payroll.status_corrected') }}</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full border border-gray-200 bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-500">{{ __('ui.payroll.status_accrued') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><x-lentera.empty-state>{{ __('ui.payroll.commissions_empty') }}</x-lentera.empty-state></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $rows->links() }}</div>
        </div>
    </div>
</x-app-layout>
