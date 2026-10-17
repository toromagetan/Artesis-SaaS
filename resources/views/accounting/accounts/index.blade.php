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
                    <x-lentera.dropdown-aksi>
                        <x-lentera.menu-item :href="route('accounts.create')">{{ __('ui.accounting.account_create') }}</x-lentera.menu-item>
                    </x-lentera.dropdown-aksi>
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
                                    <x-lentera.row-actions>
                                        <x-lentera.menu-item :href="route('accounts.show', $account)">{{ __('ui.accounting.account_detail') }}</x-lentera.menu-item>
                                        @can('accounts.manage')
                                            <x-lentera.menu-item :href="route('accounts.edit', $account)">{{ __('ui.edit') }}</x-lentera.menu-item>
                                            <div class="my-1 border-t border-gray-100"></div>
                                            <form method="POST" action="{{ route('accounts.toggle-active', $account) }}">
                                                @csrf
                                                <button type="submit" class="block w-full px-4 py-2 text-left text-sm transition duration-150 hover:bg-lentera-50 {{ $account->is_active ? 'text-red-600' : 'text-lentera-700' }}">{{ $account->is_active ? __('ui.deactivate') : __('ui.activate') }}</button>
                                            </form>
                                        @endcan
                                    </x-lentera.row-actions>
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
