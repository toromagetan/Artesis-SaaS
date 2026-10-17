<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.customers.title') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-lg border border-lentera-200 bg-lentera-50 px-4 py-3 text-sm text-lentera-800">{{ session('status') }}</div>
            @endif

            <div class="mb-4 flex items-center justify-between">
                <form method="GET" class="flex gap-2">
                    <x-text-input name="q" :value="$q" :placeholder="__('ui.customers.search_placeholder')" class="w-72" />
                    <select name="cabang" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                        <option value="">{{ __('ui.customers.home_branch') }}: {{ __('ui.search') }}</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected((string) $branchId === (string) $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                    <x-secondary-button type="submit">{{ __('ui.search') }}</x-secondary-button>
                </form>
                @can('customers.create')
                    <x-lentera.dropdown-aksi>
                        <x-lentera.menu-item :href="route('customers.create')">{{ __('ui.customers.create') }}</x-lentera.menu-item>
                    </x-lentera.dropdown-aksi>
                @endcan
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.customers.name') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.customers.phone') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.customers.home_branch') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.customers.status') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($customers as $customer)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 font-medium text-gray-800">
                                    <a href="{{ route('customers.show', $customer) }}" class="text-lentera-800 underline-offset-2 hover:underline">{{ $customer->name }}</a>
                                </td>
                                <td class="px-4 py-3">{{ $customer->phone }}</td>
                                <td class="px-4 py-3">{{ $customer->branch->name }}</td>
                                <td class="px-4 py-3"><x-lentera.badge :active="$customer->is_active" /></td>
                                <td class="px-4 py-3 text-right">
                                    <x-lentera.row-actions>
                                        <x-lentera.menu-item :href="route('customers.show', $customer)">{{ __('ui.customers.detail') }}</x-lentera.menu-item>
                                        @can('customers.update')
                                            <x-lentera.menu-item :href="route('customers.edit', $customer)">{{ __('ui.edit') }}</x-lentera.menu-item>
                                        @endcan
                                        @can('prescriptions.create')
                                            <x-lentera.menu-item :href="route('prescriptions.create', $customer)">{{ __('ui.prescriptions.create') }}</x-lentera.menu-item>
                                        @endcan
                                        @can('customers.deactivate')
                                            <div class="my-1 border-t border-gray-100"></div>
                                            <form method="POST" action="{{ route('customers.toggle-active', $customer) }}">
                                                @csrf
                                                <button type="submit" class="block w-full px-4 py-2 text-left text-sm transition duration-150 hover:bg-lentera-50 {{ $customer->is_active ? 'text-red-600' : 'text-lentera-700' }}">{{ $customer->is_active ? __('ui.deactivate') : __('ui.activate') }}</button>
                                            </form>
                                        @endcan
                                    </x-lentera.row-actions>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><x-lentera.empty-state>{{ __('ui.customers.empty') }}</x-lentera.empty-state></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $customers->links() }}</div>
        </div>
    </div>
</x-app-layout>
