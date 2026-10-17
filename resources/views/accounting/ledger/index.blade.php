<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.accounting.ledger') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="mb-4 flex items-center justify-between">
                <a href="{{ route('journals.index') }}" class="text-sm text-gray-600 underline">{{ __('ui.back') }}</a>
                @can('accounts.view')
                    <a href="{{ route('accounts.index') }}" class="text-sm text-lentera-800 underline">{{ __('ui.accounting.accounts') }}</a>
                @endcan
            </div>

            <form method="GET" class="mb-4 flex flex-wrap items-end gap-2">
                <div>
                    <x-input-label for="akun" :value="__('ui.accounting.account')" />
                    <select id="akun" name="akun" class="mt-1 block w-72 rounded-md border-gray-300 text-sm shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                        @foreach ($accounts as $option)
                            <option value="{{ $option->id }}" @selected($account && $option->id === $account->id)>{{ $option->code }} — {{ $option->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="dari" :value="__('ui.accounting.from')" />
                    <x-text-input id="dari" name="dari" type="date" :value="$from" class="mt-1 block" />
                </div>
                <div>
                    <x-input-label for="sampai" :value="__('ui.accounting.to')" />
                    <x-text-input id="sampai" name="sampai" type="date" :value="$to" class="mt-1 block" />
                </div>
                <div>
                    <x-input-label for="cabang" :value="__('ui.accounting.branch')" />
                    <select id="cabang" name="cabang" class="mt-1 block w-48 rounded-md border-gray-300 text-sm shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                        <option value="">{{ __('ui.accounting.all_branches') }}</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected($selectedBranch === $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
                <x-secondary-button type="submit">{{ __('ui.search') }}</x-secondary-button>
            </form>

            <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-lg bg-white p-4 shadow-soft">
                    <div class="text-xs text-gray-500">{{ __('ui.accounting.opening_balance') }}</div>
                    <div class="mt-1 text-base font-semibold tnum">{{ $result['opening']->format() }}</div>
                </div>
                <div class="rounded-lg bg-white p-4 shadow-soft">
                    <div class="text-xs text-gray-500">{{ __('ui.accounting.total_debit') }}</div>
                    <div class="mt-1 text-base font-semibold tnum">{{ $result['total_debit']->format() }}</div>
                </div>
                <div class="rounded-lg bg-white p-4 shadow-soft">
                    <div class="text-xs text-gray-500">{{ __('ui.accounting.total_credit') }}</div>
                    <div class="mt-1 text-base font-semibold tnum">{{ $result['total_credit']->format() }}</div>
                </div>
                <div class="rounded-lg bg-white p-4 shadow-soft">
                    <div class="text-xs text-gray-500">{{ __('ui.accounting.closing_balance') }}</div>
                    <div class="mt-1 text-base font-semibold tnum">{{ $result['closing']->format() }}</div>
                </div>
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.accounting.date') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.accounting.number') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.accounting.description') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ __('ui.accounting.debit') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ __('ui.accounting.credit') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ __('ui.accounting.balance') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($result['entries'] as $entry)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3">{{ \Carbon\Carbon::parse($entry['date'])->format('d/m/Y') }}</td>
                                <td class="px-4 py-3 font-medium">
                                    <a href="{{ route('journals.show', $entry['journal_id']) }}" class="text-lentera-800 underline-offset-2 hover:underline tnum">{{ $entry['number'] }}</a>
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ $entry['memo'] ?? $entry['description'] ?? $entry['source_type'] }}</td>
                                <td class="px-4 py-3 text-right tnum">{{ $entry['debit']->isZero() ? '—' : $entry['debit']->format() }}</td>
                                <td class="px-4 py-3 text-right tnum">{{ $entry['credit']->isZero() ? '—' : $entry['credit']->format() }}</td>
                                <td class="px-4 py-3 text-right font-medium tnum">{{ $entry['balance']->format() }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-6"><x-lentera.empty-state :message="__('ui.accounting.ledger_empty')" /></td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
