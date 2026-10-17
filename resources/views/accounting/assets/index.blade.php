<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.assets.title') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="mb-4 flex items-center justify-between">
                <span class="text-sm text-gray-500">{{ $assets->count() }} {{ __('ui.assets.title') }}</span>
                <x-lentera.dropdown-aksi>
                    <x-lentera.menu-item :href="route('assets.create')">{{ __('ui.assets.create') }}</x-lentera.menu-item>
                    <form method="POST" action="{{ route('assets.depreciate') }}">
                        @csrf
                        <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50">{{ __('ui.assets.run_depreciation') }}</button>
                    </form>
                </x-lentera.dropdown-aksi>
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.assets.name') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.assets.acquired_at') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ __('ui.assets.cost') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ __('ui.assets.life_months') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ __('ui.assets.accumulated') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ __('ui.assets.book_value') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.assets.status') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($assets as $asset)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 font-medium text-gray-800">{{ $asset->name }}</td>
                                <td class="px-4 py-3">{{ $asset->acquired_at->format('d/m/Y') }}</td>
                                <td class="px-4 py-3 text-right tnum">{{ $asset->cost()->format() }}</td>
                                <td class="px-4 py-3 text-right tnum">{{ $asset->useful_life_months }}</td>
                                <td class="px-4 py-3 text-right tnum">{{ $asset->accumulated()->format() }}</td>
                                <td class="px-4 py-3 text-right font-medium tnum">{{ $asset->bookValue()->format() }}</td>
                                <td class="px-4 py-3">
                                    @if ($asset->status === 'active')
                                        <x-lentera.badge tone="green">{{ __('ui.assets.active') }}</x-lentera.badge>
                                    @else
                                        <x-lentera.badge tone="gray">{{ __('ui.assets.disposed') }}</x-lentera.badge>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <x-lentera.row-actions>
                                        <x-lentera.menu-item :href="route('assets.show', $asset)">{{ __('ui.detail') }}</x-lentera.menu-item>
                                    </x-lentera.row-actions>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-6"><x-lentera.empty-state :message="__('ui.assets.empty')" /></td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
