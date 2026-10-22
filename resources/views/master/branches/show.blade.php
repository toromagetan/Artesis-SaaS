<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.branches.detail') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
            <div class="mb-4 flex items-center justify-between">
                <a href="{{ route('branches.index') }}" class="text-sm text-gray-600 underline">{{ __('ui.back') }}</a>
                <x-lentera.aksi-bar>
                    <x-lentera.menu-item :href="route('branches.create')">{{ __('ui.branches.create') }}</x-lentera.menu-item>
                    <x-lentera.menu-item :href="route('branches.edit', $branch)">{{ __('ui.edit') }}</x-lentera.menu-item>

                    <form method="POST" action="{{ route('branches.toggle-active', $branch) }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-sm font-medium shadow-soft transition duration-150 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-lentera-500 {{ $branch->is_active ? 'text-red-600' : 'text-lentera-700' }}">{{ $branch->is_active ? __('ui.deactivate') : __('ui.activate') }}</button>
                    </form>
                </x-lentera.aksi-bar>
            </div>

            <div class="rounded-lg bg-white p-6 shadow-soft">
                <div class="mb-4 flex items-center gap-3">
                    <h2 class="text-lg font-semibold text-gray-800">{{ $branch->name }}</h2>
                    <x-lentera.badge :active="$branch->is_active" />
                </div>
                <dl class="grid grid-cols-[160px_1fr] gap-y-3 text-sm">
                    <dt class="text-gray-500">{{ __('ui.branches.phone') }}</dt>
                    <dd class="font-medium text-gray-800">{{ $branch->phone ?: '—' }}</dd>
                    <dt class="text-gray-500">{{ __('ui.branches.address_optional') }}</dt>
                    <dd class="font-medium text-gray-800">{{ $branch->address ?: '—' }}</dd>
                    <dt class="text-gray-500">{{ __('ui.branches.user_count') }}</dt>
                    <dd class="font-medium text-gray-800 tnum">{{ $branch->users_count }}</dd>
                </dl>
            </div>
        </div>
    </div>
</x-app-layout>
