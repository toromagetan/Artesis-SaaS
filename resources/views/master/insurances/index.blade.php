<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.insurances.title') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-lg border border-lentera-200 bg-lentera-50 px-4 py-3 text-sm text-lentera-800">{{ session('status') }}</div>
            @endif

            <div class="mb-4 flex justify-end">
                <x-lentera.dropdown-aksi>
                    <x-lentera.menu-item :href="route('insurances.create')">{{ __('ui.insurances.create') }}</x-lentera.menu-item>
                </x-lentera.dropdown-aksi>
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.insurances.name') }}</th>
                            <th class="px-4 py-3 font-medium text-right">{{ __('ui.insurances.claim_value') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.insurances.status') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($insurances as $insurance)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 font-medium text-gray-800">{{ $insurance->name }}</td>
                                <td class="px-4 py-3 text-right tnum">{{ \App\Support\Money::fromDecimal((string) $insurance->claim_value)->format() }}</td>
                                <td class="px-4 py-3"><x-lentera.badge :active="$insurance->is_active" /></td>
                                <td class="px-4 py-3 text-right">
                                    <x-lentera.row-actions>
                                        <x-lentera.menu-item :href="route('insurances.edit', $insurance)">{{ __('ui.edit') }}</x-lentera.menu-item>
                                        <div class="my-1 border-t border-gray-100"></div>
                                        <form method="POST" action="{{ route('insurances.toggle-active', $insurance) }}">
                                            @csrf
                                            <button type="submit" class="block w-full px-4 py-2 text-left text-sm transition duration-150 hover:bg-lentera-50 {{ $insurance->is_active ? 'text-red-600' : 'text-lentera-700' }}">{{ $insurance->is_active ? __('ui.deactivate') : __('ui.activate') }}</button>
                                        </form>
                                    </x-lentera.row-actions>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4"><x-lentera.empty-state>{{ __('ui.insurances.empty') }}</x-lentera.empty-state></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $insurances->links() }}</div>
        </div>
    </div>
</x-app-layout>
