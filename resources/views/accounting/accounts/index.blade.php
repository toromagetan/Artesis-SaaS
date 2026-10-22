<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.accounting.accounts') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-lg border border-lentera-200 bg-lentera-50 px-4 py-3 text-sm text-lentera-800">{{ session('status') }}</div>
            @endif

            <div class="mb-4 flex items-center justify-between">
                <a href="{{ route('journals.index') }}" class="text-sm text-gray-600 underline">{{ __('ui.back') }}</a>
                @can('accounts.manage')
                    <x-lentera.aksi-bar>
                        <x-lentera.menu-item :href="route('accounts.create')" primary>{{ __('ui.accounting.account_create') }}</x-lentera.menu-item>
                    </x-lentera.aksi-bar>
                @endcan
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.accounting.code') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.accounting.name') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.accounting.parent') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.accounting.group') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.accounting.status') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($accounts as $account)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 font-medium tnum">{{ $account->code }}</td>
                                <td class="px-4 py-3 text-gray-800">
                                    <a href="{{ route('accounts.show', $account) }}" class="text-lentera-800 underline-offset-2 hover:underline">{{ $account->name }}</a>
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ $account->parent ? $account->parent->code.' — '.$account->parent->name : '—' }}</td>
                                <td class="px-4 py-3">{{ __('ui.accounting.groups.'.$account->group) }}</td>
                                <td class="px-4 py-3"><x-lentera.badge :active="$account->is_active" /></td>
                                <td class="px-4 py-3 text-right">
                                    <x-lentera.aksi-baris>
                                        <x-lentera.menu-item :href="route('accounts.show', $account)" sm>{{ __('ui.accounting.account_detail') }}</x-lentera.menu-item>
                                        @can('accounts.manage')
                                            <x-lentera.menu-item :href="route('accounts.edit', $account)" sm>{{ __('ui.edit') }}</x-lentera.menu-item>

                                            <form method="POST" action="{{ route('accounts.toggle-active', $account) }}">
                                                @csrf
                                                <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-[13px] font-medium shadow-soft transition duration-150 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-lentera-500 {{ $account->is_active ? 'text-red-600' : 'text-lentera-700' }}">{{ $account->is_active ? __('ui.deactivate') : __('ui.activate') }}</button>
                                            </form>
                                        @endcan
                                    </x-lentera.aksi-baris>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-6"><x-lentera.empty-state :message="__('ui.accounting.empty_accounts')" /></td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
