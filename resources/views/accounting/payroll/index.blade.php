<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.payroll.title') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <form method="GET" action="{{ route('payroll.index') }}" class="flex items-end gap-2">
                    <div>
                        <x-input-label for="periode" :value="__('ui.periods.period')" />
                        <x-text-input id="periode" name="periode" type="month" value="{{ $period }}" />
                    </div>
                    <x-secondary-button type="submit">{{ __('ui.reports.apply') }}</x-secondary-button>
                </form>
                @unless ($allPosted)
                    <x-lentera.dropdown-aksi>
                        <form method="POST" action="{{ route('payroll.post') }}">
                            @csrf
                            <input type="hidden" name="period" value="{{ $period }}">
                            <input type="hidden" name="method" value="cash">
                            <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-lentera-50">{{ __('ui.payroll.post_period_cash') }}</button>
                        </form>
                        <form method="POST" action="{{ route('payroll.post') }}">
                            @csrf
                            <input type="hidden" name="period" value="{{ $period }}">
                            <input type="hidden" name="method" value="transfer">
                            <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-lentera-50">{{ __('ui.payroll.post_period_transfer') }}</button>
                        </form>
                        <x-lentera.menu-item :href="route('commissions.index')">{{ __('ui.payroll.commissions_link') }}</x-lentera.menu-item>
                    </x-lentera.dropdown-aksi>
                @else
                    <a href="{{ route('commissions.index') }}" class="text-sm text-lentera-800 underline">{{ __('ui.payroll.commissions_link') }}</a>
                @endunless
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.payroll.employee') }}</th>
                            <th class="px-4 py-3 font-medium text-right">{{ __('ui.payroll.base') }}</th>
                            <th class="px-4 py-3 font-medium text-right">{{ __('ui.payroll.allowance') }}</th>
                            <th class="px-4 py-3 font-medium text-right">{{ __('ui.payroll.deduction') }}</th>
                            <th class="px-4 py-3 font-medium text-right">{{ __('ui.payroll.net') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.payroll.journal') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($entries as $entry)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 font-medium">{{ $entry->employee?->name }}</td>
                                <td class="px-4 py-3 text-right tnum">{{ \App\Support\Money::fromDecimal((string) $entry->base_amount)->format() }}</td>
                                <td class="px-4 py-3 text-right tnum">{{ \App\Support\Money::fromDecimal((string) $entry->allowance_amount)->format() }}</td>
                                <td class="px-4 py-3 text-right tnum">{{ \App\Support\Money::fromDecimal((string) $entry->deduction_amount)->format() }}</td>
                                <td class="px-4 py-3 text-right font-medium tnum">{{ $entry->net()->format() }}</td>
                                <td class="px-4 py-3">
                                    @if ($entry->journal_id)
                                        <a href="{{ route('journals.show', $entry->journal_id) }}" class="text-lentera-800 underline">{{ __('ui.payroll.posted_badge') }}</a>
                                    @else
                                        <span class="inline-flex items-center rounded-full border border-gray-200 bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-500">{{ __('ui.payroll.unposted') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><x-lentera.empty-state>{{ __('ui.payroll.empty') }}</x-lentera.empty-state></td></tr>
                        @endforelse
                    </tbody>
                    @if ($entries->isNotEmpty())
                        <tfoot>
                            <tr class="border-t border-gray-100 font-medium">
                                <td class="px-4 py-3" colspan="4">{{ __('ui.payroll.total_net') }}</td>
                                <td class="px-4 py-3 text-right tnum">{{ $totalNetFormatted }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>

            @unless ($allPosted)
                <div class="mt-6 rounded-lg bg-white p-6 shadow-soft">
                    <h2 class="text-base font-semibold text-lentera-950">{{ __('ui.payroll.entry_form') }}</h2>
                    <form method="POST" action="{{ route('payroll.store') }}" class="mt-4 grid gap-4 md:grid-cols-2">
                        @csrf
                        <input type="hidden" name="period" value="{{ $period }}">
                        <div>
                            <x-input-label for="employee_user_id" :value="__('ui.payroll.employee')" />
                            <select id="employee_user_id" name="employee_user_id" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500" required>
                                @foreach ($employees as $employee)
                                    <option value="{{ $employee->id }}">{{ $employee->name }} ({{ $employee->username }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="base_amount" :value="__('ui.payroll.base')" />
                            <x-text-input id="base_amount" name="base_amount" type="number" step="0.01" min="0" class="mt-1 block w-full" required />
                        </div>
                        <div>
                            <x-input-label for="allowance_amount" :value="__('ui.payroll.allowance')" />
                            <x-text-input id="allowance_amount" name="allowance_amount" type="number" step="0.01" min="0" class="mt-1 block w-full" />
                        </div>
                        <div>
                            <x-input-label for="deduction_amount" :value="__('ui.payroll.deduction')" />
                            <x-text-input id="deduction_amount" name="deduction_amount" type="number" step="0.01" min="0" class="mt-1 block w-full" />
                        </div>
                        <div class="md:col-span-2">
                            <x-primary-button>{{ __('ui.payroll.save_entry') }}</x-primary-button>
                        </div>
                    </form>
                </div>
            @endunless
        </div>
    </div>
</x-app-layout>
