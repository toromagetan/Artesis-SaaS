<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.roles.title') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-lg border border-lentera-200 bg-lentera-50 px-4 py-3 text-sm text-lentera-800">{{ session('status') }}</div>
            @endif

            @if ($canCreate)
                <form method="POST" action="{{ route('roles.store') }}" class="mb-4 flex flex-wrap items-end gap-3 rounded-lg bg-white p-4 shadow-soft">
                    @csrf
                    <label class="flex flex-col gap-1 text-sm">
                        <span class="text-gray-500">{{ __('ui.roles.machine_name') }}</span>
                        <input type="text" name="name" required maxlength="50" class="rounded-lg border-gray-200 text-sm" placeholder="kasir-senior">
                    </label>
                    <label class="flex flex-col gap-1 text-sm">
                        <span class="text-gray-500">{{ __('ui.roles.display_label') }}</span>
                        <input type="text" name="label" required maxlength="100" class="rounded-lg border-gray-200 text-sm" placeholder="Kasir Senior">
                    </label>
                    <button type="submit" class="rounded-lg bg-lentera-600 px-4 py-2 text-sm font-medium text-white hover:bg-lentera-700">{{ __('ui.roles.create') }}</button>
                    @error('name') <p class="w-full text-sm text-red-600">{{ $message }}</p> @enderror
                </form>
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
                                <td class="px-4 py-3 font-medium text-gray-800">{{ $role->label }} <span class="text-xs text-gray-400">({{ $role->name }})</span>@if ($role->tenant_id === null) <span class="ml-1 rounded bg-gray-100 px-1.5 py-0.5 text-[11px] font-normal text-gray-500">{{ __('ui.roles.template_badge') }}</span>@endif</td>
                                <td class="px-4 py-3 tnum">{{ $role->permissions_count }}</td>
                                <td class="px-4 py-3 tnum">{{ $role->users_count }}</td>
                                <td class="px-4 py-3 text-right">
                                    @if ($role->isEditableBy(auth()->user()))
                                        <x-lentera.aksi-baris>
                                            <x-lentera.menu-item :href="route('roles.edit', $role)" sm>{{ __('ui.roles.edit') }}</x-lentera.menu-item>
                                        </x-lentera.aksi-baris>
                                    @endif
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
