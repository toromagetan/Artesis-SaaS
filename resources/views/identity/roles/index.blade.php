<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.roles.title') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-lg border border-lentera-200 bg-lentera-50 px-4 py-3 text-sm text-lentera-800">{{ session('status') }}</div>
            @endif

            <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.roles.role') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.roles.permission_count') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.roles.user_count') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($roles as $role)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 font-medium text-gray-800">{{ $role->label }} <span class="text-xs text-gray-400">({{ $role->name }})</span></td>
                                <td class="px-4 py-3 tnum">{{ $role->permissions_count }}</td>
                                <td class="px-4 py-3 tnum">{{ $role->users_count }}</td>
                                <td class="px-4 py-3 text-right">
                                    <x-lentera.aksi-baris>
                                        <x-lentera.menu-item :href="route('roles.edit', $role)" sm>{{ __('ui.roles.edit') }}</x-lentera.menu-item>
                                    </x-lentera.aksi-baris>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4"><x-lentera.empty-state>{{ __('ui.roles.empty') }}</x-lentera.empty-state></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
