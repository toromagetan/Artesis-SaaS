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
                    <x-lentera.aksi-bar>
                        <x-lentera.menu-item :href="route('customers.create')" primary>{{ __('ui.customers.create') }}</x-lentera.menu-item>
                    </x-lentera.aksi-bar>
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
                                    <x-lentera.aksi-baris>
                                        <x-lentera.menu-item :href="route('customers.show', $customer)" sm>{{ __('ui.customers.detail') }}</x-lentera.menu-item>
                                        @can('customers.update')
                                            <x-lentera.menu-item :href="route('customers.edit', $customer)" sm>{{ __('ui.edit') }}</x-lentera.menu-item>
                                        @endcan
                                        @can('prescriptions.create')
                                            <x-lentera.menu-item :href="route('prescriptions.create', $customer)" sm>{{ __('ui.prescriptions.create') }}</x-lentera.menu-item>
                                        @endcan
                                        @can('customers.deactivate')

                                            <form method="POST" action="{{ route('customers.toggle-active', $customer) }}">
                                                @csrf
                                                <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-[13px] font-medium shadow-soft transition duration-150 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-lentera-500 {{ $customer->is_active ? 'text-red-600' : 'text-lentera-700' }}">{{ $customer->is_active ? __('ui.deactivate') : __('ui.activate') }}</button>
                                            </form>
                                        @endcan
                                    </x-lentera.aksi-baris>
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
