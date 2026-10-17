<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.users.title') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-lg border border-lentera-200 bg-lentera-50 px-4 py-3 text-sm text-lentera-800">{{ session('status') }}</div>
            @endif

            <div class="mb-4 flex items-center justify-between">
                <form method="GET" class="flex gap-2">
                    <x-text-input name="q" :value="$q" :placeholder="__('ui.users.search_placeholder')" class="w-72" />
                    <x-secondary-button type="submit">{{ __('ui.search') }}</x-secondary-button>
                </form>
                <div class="flex items-center gap-2">
                    <x-lentera.dropdown-cetak>
                        <x-lentera.menu-item :href="route('users.print')" target="_blank">{{ __('ui.print_doc.user_list') }}</x-lentera.menu-item>
                    </x-lentera.dropdown-cetak>
                    <x-lentera.dropdown-aksi>
                        <x-lentera.menu-item :href="route('users.create')">{{ __('ui.users.create') }}</x-lentera.menu-item>
                    </x-lentera.dropdown-aksi>
                </div>
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.users.name') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.users.username') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.users.roles') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.users.branches') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.users.status') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $user)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 font-medium text-gray-800">
                                    <a href="{{ route('users.show', $user) }}" class="text-lentera-800 underline-offset-2 hover:underline">{{ $user->name }}</a>@if ($user->is_platform) <span class="text-xs text-gray-400">(platform)</span>@endif
                                </td>
                                <td class="px-4 py-3">{{ $user->username }}</td>
                                <td class="px-4 py-3">{{ $user->roles->pluck('label')->join(', ') ?: '—' }}</td>
                                <td class="px-4 py-3">{{ $user->hasGlobalAccess() ? __('ui.users.all_branches') : ($user->branches->pluck('name')->join(', ') ?: '—') }}</td>
                                <td class="px-4 py-3"><x-lentera.badge :active="$user->is_active" /></td>
                                <td class="px-4 py-3 text-right">
                                    <x-lentera.row-actions>
                                        <x-lentera.menu-item :href="route('users.show', $user)">{{ __('ui.users.detail') }}</x-lentera.menu-item>
                                        <x-lentera.menu-item :href="route('users.edit', $user)">{{ __('ui.edit') }}</x-lentera.menu-item>
                                        <div class="my-1 border-t border-gray-100"></div>
                                        <form method="POST" action="{{ route('users.toggle-active', $user) }}">
                                            @csrf
                                            <button type="submit" class="block w-full px-4 py-2 text-left text-sm transition duration-150 hover:bg-lentera-50 {{ $user->is_active ? 'text-red-600' : 'text-lentera-700' }}">{{ $user->is_active ? __('ui.deactivate') : __('ui.activate') }}</button>
                                        </form>
                                    </x-lentera.row-actions>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><x-lentera.empty-state>{{ __('ui.users.empty') }}</x-lentera.empty-state></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $users->links() }}</div>
        </div>
    </div>
</x-app-layout>
