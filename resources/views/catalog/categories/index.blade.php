<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.categories.title') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-lg border border-lentera-200 bg-lentera-50 px-4 py-3 text-sm text-lentera-800">{{ session('status') }}</div>
            @endif

            <div class="mb-4 flex items-center justify-end">
                @can('products.create')
                    <x-lentera.dropdown-aksi>
                        <x-lentera.menu-item :href="route('products.categories.create')">{{ __('ui.categories.create') }}</x-lentera.menu-item>
                    </x-lentera.dropdown-aksi>
                @endcan
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.categories.name') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.categories.type') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.categories.description') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.categories.parents_count') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.products.status') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($categories as $category)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 font-medium text-gray-800">{{ $category->name }}</td>
                                <td class="px-4 py-3">{{ $category->typeLabel() }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $category->description ?: '—' }}</td>
                                <td class="px-4 py-3 tnum">{{ $category->parents_count }}</td>
                                <td class="px-4 py-3"><x-lentera.badge :active="$category->is_active" /></td>
                                <td class="px-4 py-3 text-right">
                                    <x-lentera.row-actions>
                                        @can('products.update')
                                            <x-lentera.menu-item :href="route('products.categories.edit', $category)">{{ __('ui.edit') }}</x-lentera.menu-item>
                                        @endcan
                                        @can('products.deactivate')
                                            <div class="my-1 border-t border-gray-100"></div>
                                            <form method="POST" action="{{ route('products.categories.toggle-active', $category) }}">
                                                @csrf
                                                <button type="submit" class="block w-full px-4 py-2 text-left text-sm transition duration-150 hover:bg-lentera-50 {{ $category->is_active ? 'text-red-600' : 'text-lentera-700' }}">{{ $category->is_active ? __('ui.deactivate') : __('ui.activate') }}</button>
                                            </form>
                                        @endcan
                                    </x-lentera.row-actions>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><x-lentera.empty-state>{{ __('ui.categories.empty') }}</x-lentera.empty-state></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
