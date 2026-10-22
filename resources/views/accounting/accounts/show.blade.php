<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.accounting.account_detail') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-lg border border-lentera-200 bg-lentera-50 px-4 py-3 text-sm text-lentera-800">{{ session('status') }}</div>
            @endif

            <div class="mb-4 flex items-center justify-between">
                <a href="{{ route('accounts.index') }}" class="text-sm text-gray-600 underline">{{ __('ui.back') }}</a>
                @can('accounts.manage')
                    <x-lentera.aksi-bar>
                        <x-lentera.menu-item :href="route('accounts.edit', $account)">{{ __('ui.edit') }}</x-lentera.menu-item>

                        <form method="POST" action="{{ route('accounts.toggle-active', $account) }}">
                            @csrf
                            <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-sm font-medium shadow-soft transition duration-150 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-lentera-500 {{ $account->is_active ? 'text-red-600' : 'text-lentera-700' }}">{{ $account->is_active ? __('ui.deactivate') : __('ui.activate') }}</button>
                        </form>
                    </x-lentera.aksi-bar>
                @endcan
            </div>

            <div class="rounded-lg bg-white p-6 shadow-soft">
                <dl class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <dt class="text-sm text-gray-500">{{ __('ui.accounting.code') }}</dt>
                        <dd class="font-medium text-gray-800 tnum">{{ $account->code }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500">{{ __('ui.accounting.name') }}</dt>
                        <dd class="font-medium text-gray-800">{{ $account->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500">{{ __('ui.accounting.group') }}</dt>
                        <dd class="font-medium text-gray-800">{{ __('ui.accounting.groups.'.$account->group) }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500">{{ __('ui.accounting.parent') }}</dt>
                        <dd class="font-medium text-gray-800">{{ $account->parent ? $account->parent->code.' — '.$account->parent->name : '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500">{{ __('ui.accounting.status') }}</dt>
                        <dd><x-lentera.badge :active="$account->is_active" /></dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500">{{ __('ui.accounting.line_count') }}</dt>
                        <dd class="font-medium text-gray-800 tnum">{{ $totals->line_count }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500">{{ __('ui.accounting.total_debit') }}</dt>
                        <dd class="font-medium text-gray-800 tnum">{{ \App\Support\Money::fromDecimal((string) $totals->total_debit)->format() }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500">{{ __('ui.accounting.total_credit') }}</dt>
                        <dd class="font-medium text-gray-800 tnum">{{ \App\Support\Money::fromDecimal((string) $totals->total_credit)->format() }}</dd>
                    </div>
                </dl>

                @if ($account->children->isNotEmpty())
                    <h2 class="mt-6 text-sm font-semibold text-gray-700">{{ __('ui.accounting.children') }}</h2>
                    <ul class="mt-2 divide-y divide-gray-50 text-sm">
                        @foreach ($account->children as $child)
                            <li class="py-2">
                                <a href="{{ route('accounts.show', $child) }}" class="text-lentera-800 underline-offset-2 hover:underline"><span class="tnum">{{ $child->code }}</span> — {{ $child->name }}</a>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <h2 class="mt-6 text-sm font-semibold text-gray-700">{{ __('ui.accounting.recent_lines') }}</h2>
                <table class="mt-2 min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-2 py-2 font-medium">{{ __('ui.accounting.date') }}</th>
                            <th class="px-2 py-2 font-medium">{{ __('ui.accounting.number') }}</th>
                            <th class="px-2 py-2 text-right font-medium">{{ __('ui.accounting.debit') }}</th>
                            <th class="px-2 py-2 text-right font-medium">{{ __('ui.accounting.credit') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentLines as $line)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-2 py-2">{{ $line->journal->journal_date->format('d/m/Y') }}</td>
                                <td class="px-2 py-2">
                                    <a href="{{ route('journals.show', $line->journal) }}" class="text-lentera-800 underline-offset-2 hover:underline tnum">{{ $line->journal->number }}</a>
                                </td>
                                <td class="px-2 py-2 text-right tnum">{{ \App\Support\Money::fromDecimal((string) $line->debit)->format() }}</td>
                                <td class="px-2 py-2 text-right tnum">{{ \App\Support\Money::fromDecimal((string) $line->credit)->format() }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-2 py-4 text-gray-500">{{ __('ui.accounting.no_lines') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
