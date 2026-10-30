<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.prescriptions.pick_customer') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <p class="mb-4 text-sm text-gray-500">{{ __('ui.prescriptions.pick_customer_hint') }}</p>

            <div class="mb-4 flex items-center justify-between">
                <form method="GET" class="flex gap-2">
                    <x-text-input name="q" :value="$q" :placeholder="__('ui.customers.search_placeholder')" class="w-72" />
                    <x-secondary-button type="submit">{{ __('ui.search') }}</x-secondary-button>
                </form>
                @can('customers.create')
                    <x-lentera.aksi-bar>
                        <x-lentera.menu-item :href="route('customers.create')" primary>{{ __('ui.customers.create') }}</x-lentera.menu-item>
                    </x-lentera.aksi-bar>
                @endcan
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-xs uppercase tracking-wide text-gray-400">
                            <th class="px-4 py-3">{{ __('ui.customers.name') }}</th>
                            <th class="px-4 py-3">{{ __('ui.customers.phone') }}</th>
                            <th class="px-4 py-3">{{ __('ui.customers.home_branch') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('ui.action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($customers as $customer)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 font-medium text-gray-800">{{ $customer->name }}</td>
                                <td class="px-4 py-3">{{ $customer->phone }}</td>
                                <td class="px-4 py-3">{{ $customer->branch?->name }}</td>
                                <td class="px-4 py-3 text-right">
                                    <x-lentera.aksi-bar>
                                        <x-lentera.menu-item :href="route('prescriptions.create', $customer)" primary sm>{{ __('ui.prescriptions.create') }}</x-lentera.menu-item>
                                    </x-lentera.aksi-bar>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4"><x-lentera.empty-state>{{ __('ui.customers.empty') }}</x-lentera.empty-state></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $customers->links() }}</div>
        </div>
    </div>
</x-app-layout>
