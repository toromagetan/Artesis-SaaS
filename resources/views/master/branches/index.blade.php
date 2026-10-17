<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.branches.title') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-lg border border-lentera-200 bg-lentera-50 px-4 py-3 text-sm text-lentera-800">{{ session('status') }}</div>
            @endif

            <div class="mb-4 flex justify-end">
                <x-lentera.dropdown-aksi>
                    <x-lentera.menu-item :href="route('branches.create')">{{ __('ui.branches.create') }}</x-lentera.menu-item>
                </x-lentera.dropdown-aksi>
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.branches.name') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.branches.phone') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.branches.status') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($branches as $branch)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 font-medium text-gray-800">
                                    <a href="{{ route('branches.show', $branch) }}" class="text-lentera-800 underline-offset-2 hover:underline">{{ $branch->name }}</a>
                                </td>
                                <td class="px-4 py-3">{{ $branch->phone ?: '—' }}</td>
                                <td class="px-4 py-3"><x-lentera.badge :active="$branch->is_active" /></td>
                                <td class="px-4 py-3 text-right">
                                    <x-lentera.row-actions>
                                        <x-lentera.menu-item :href="route('branches.show', $branch)">{{ __('ui.branches.detail') }}</x-lentera.menu-item>
                                        <x-lentera.menu-item :href="route('branches.edit', $branch)">{{ __('ui.edit') }}</x-lentera.menu-item>
                                        <div class="my-1 border-t border-gray-100"></div>
                                        <form method="POST" action="{{ route('branches.toggle-active', $branch) }}">
                                            @csrf
                                            <button type="submit" class="block w-full px-4 py-2 text-left text-sm transition duration-150 hover:bg-lentera-50 {{ $branch->is_active ? 'text-red-600' : 'text-lentera-700' }}">{{ $branch->is_active ? __('ui.deactivate') : __('ui.activate') }}</button>
                                        </form>
                                    </x-lentera.row-actions>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4"><x-lentera.empty-state>{{ __('ui.branches.empty') }}</x-lentera.empty-state></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $branches->links() }}</div>
        </div>
    </div>
</x-app-layout>
