<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.users.detail') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-lg border border-lentera-200 bg-lentera-50 px-4 py-3 text-sm text-lentera-800">{{ session('status') }}</div>
            @endif

            <div class="mb-4 flex items-center justify-between">
                <a href="{{ route('users.index') }}" class="text-sm text-gray-600 underline">{{ __('ui.back') }}</a>
                <div class="flex items-center gap-2">
                    <x-lentera.dropdown-cetak>
                        <x-lentera.menu-item :href="route('users.print-profile', $user)" target="_blank">{{ __('ui.print_doc.user_profile') }}</x-lentera.menu-item>
                        <x-lentera.menu-item :href="route('users.print')" target="_blank">{{ __('ui.print_doc.user_list') }}</x-lentera.menu-item>
                    </x-lentera.dropdown-cetak>
                    <x-lentera.dropdown-aksi>
                        <x-lentera.menu-item :href="route('users.create')">{{ __('ui.users.create') }}</x-lentera.menu-item>
                        <x-lentera.menu-item :href="route('users.edit', $user)">{{ __('ui.edit') }}</x-lentera.menu-item>
                        <div class="my-1 border-t border-gray-100"></div>
                        <form method="POST" action="{{ route('users.toggle-active', $user) }}">
                            @csrf
                            <button type="submit" class="block w-full px-4 py-2 text-left text-sm transition duration-150 hover:bg-lentera-50 {{ $user->is_active ? 'text-red-600' : 'text-lentera-700' }}">{{ $user->is_active ? __('ui.deactivate') : __('ui.activate') }}</button>
                        </form>
                    </x-lentera.dropdown-aksi>
                </div>
            </div>

            <div class="rounded-lg bg-white p-6 shadow-soft">
                <div class="mb-4 flex items-center gap-3">
                    <h2 class="text-lg font-semibold text-gray-800">{{ $user->name }}</h2>
                    <x-lentera.badge :active="$user->is_active" />
                    @if ($user->is_platform)<span class="text-xs text-gray-400">(platform)</span>@endif
                </div>
                <dl class="grid grid-cols-[140px_1fr] gap-y-3 text-sm">
                    <dt class="text-gray-500">{{ __('ui.users.username') }}</dt>
                    <dd class="font-medium text-gray-800">{{ $user->username }}</dd>
                    <dt class="text-gray-500">{{ __('ui.users.email') }}</dt>
                    <dd class="font-medium text-gray-800">{{ $user->email ?: '—' }}</dd>
                    <dt class="text-gray-500">{{ __('ui.users.phone') }}</dt>
                    <dd class="font-medium text-gray-800">{{ $user->phone ?: '—' }}</dd>
                    <dt class="text-gray-500">{{ __('ui.users.roles') }}</dt>
                    <dd class="font-medium text-gray-800">{{ $user->roles->pluck('label')->join(', ') ?: '—' }}</dd>
                    <dt class="text-gray-500">{{ __('ui.users.branches') }}</dt>
                    <dd class="font-medium text-gray-800">{{ $user->hasGlobalAccess() ? __('ui.users.all_branches') : ($user->branches->pluck('name')->join(', ') ?: '—') }}</dd>
                    <dt class="text-gray-500">{{ __('ui.users.last_login') }}</dt>
                    <dd class="font-medium text-gray-800 tnum">{{ $user->last_login_at?->format('d/m/Y H:i') ?? __('ui.users.never') }}</dd>
                    <dt class="text-gray-500">{{ __('ui.users.member_since') }}</dt>
                    <dd class="font-medium text-gray-800 tnum">{{ $user->created_at?->format('d/m/Y') }}</dd>
                </dl>
            </div>
        </div>
    </div>
</x-app-layout>
