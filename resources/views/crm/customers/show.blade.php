<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.customers.detail') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-lg border border-lentera-200 bg-lentera-50 px-4 py-3 text-sm text-lentera-800">{{ session('status') }}</div>
            @endif
            @if (session('warning'))
                <div class="mb-4 rounded-lg border border-gold/50 bg-gold/10 px-4 py-3 text-sm text-gold-dark">{{ session('warning') }}</div>
            @endif

            <div class="mb-4 flex items-center justify-between">
                <a href="{{ route('customers.index') }}" class="text-sm text-gray-600 underline">{{ __('ui.back') }}</a>
                @if (auth()->user()->hasPermission('customers.create') || auth()->user()->hasPermission('prescriptions.create') || auth()->user()->hasPermission('customers.update') || auth()->user()->hasPermission('customers.deactivate'))
                    <x-lentera.dropdown-aksi>
                        @can('customers.create')
                            <x-lentera.menu-item :href="route('customers.create')">{{ __('ui.customers.create') }}</x-lentera.menu-item>
                        @endcan
                        @can('prescriptions.create')
                            <x-lentera.menu-item :href="route('prescriptions.create', $customer)">{{ __('ui.prescriptions.create') }}</x-lentera.menu-item>
                        @endcan
                        @can('customers.update')
                            <x-lentera.menu-item :href="route('customers.edit', $customer)">{{ __('ui.edit') }}</x-lentera.menu-item>
                        @endcan
                        @can('customers.deactivate')
                            <div class="my-1 border-t border-gray-100"></div>
                            <form method="POST" action="{{ route('customers.toggle-active', $customer) }}">
                                @csrf
                                <button type="submit" class="block w-full px-4 py-2 text-left text-sm transition duration-150 hover:bg-lentera-50 {{ $customer->is_active ? 'text-red-600' : 'text-lentera-700' }}">{{ $customer->is_active ? __('ui.deactivate') : __('ui.activate') }}</button>
                            </form>
                        @endcan
                    </x-lentera.dropdown-aksi>
                @endif
            </div>

            <div class="mb-6 rounded-lg bg-white p-6 shadow-soft">
                <div class="mb-4 flex items-center gap-3">
                    <h2 class="text-lg font-semibold text-gray-800">{{ $customer->name }}</h2>
                    <x-lentera.badge :active="$customer->is_active" />
                </div>
                <dl class="grid grid-cols-[160px_1fr] gap-y-3 text-sm">
                    <dt class="text-gray-500">{{ __('ui.customers.phone') }}</dt>
                    <dd class="font-medium text-gray-800">{{ $customer->phone }}</dd>
                    <dt class="text-gray-500">{{ __('ui.customers.email') }}</dt>
                    <dd class="font-medium text-gray-800">{{ $customer->email ?: '—' }}</dd>
                    <dt class="text-gray-500">{{ __('ui.customers.address') }}</dt>
                    <dd class="font-medium text-gray-800">{{ $customer->address ?: '—' }}</dd>
                    <dt class="text-gray-500">{{ __('ui.customers.birth_date') }}</dt>
                    <dd class="font-medium text-gray-800">{{ $customer->birth_date?->format('d/m/Y') ?: '—' }}</dd>
                    <dt class="text-gray-500">{{ __('ui.customers.home_branch') }}</dt>
                    <dd class="font-medium text-gray-800">{{ $customer->branch->name }}</dd>
                    <dt class="text-gray-500">{{ __('ui.customers.insurance') }}</dt>
                    <dd class="font-medium text-gray-800">{{ $customer->insurance?->name ?: '—' }}</dd>
                </dl>
            </div>

            <h2 class="mb-2 text-base font-semibold text-gray-800">{{ __('ui.customers.prescriptions_history') }}</h2>
            <div class="mb-6 overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.prescriptions.examined_at') }}</th>
                            <th class="px-4 py-3 font-medium">OD</th>
                            <th class="px-4 py-3 font-medium">OS</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.prescriptions.examiner') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($customer->prescriptions as $rx)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3">
                                    <a href="{{ route('prescriptions.show', $rx) }}" class="font-medium text-lentera-800 underline-offset-2 hover:underline">{{ $rx->examined_at->format('d/m/Y') }}</a>
                                    @if ($rx->isLocked())
                                        <span class="ms-2 inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500">{{ __('ui.prescriptions.used_badge') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 tnum">{{ $rx->od_sph }} / {{ $rx->od_cyl }} × {{ $rx->od_axis ?? '—' }}</td>
                                <td class="px-4 py-3 tnum">{{ $rx->os_sph }} / {{ $rx->os_cyl }} × {{ $rx->os_axis ?? '—' }}</td>
                                <td class="px-4 py-3">{{ $rx->examiner?->name ?: '—' }}</td>
                                <td class="px-4 py-3 text-right">
                                    <x-lentera.row-actions>
                                        <x-lentera.menu-item :href="route('prescriptions.show', $rx)">{{ __('ui.prescriptions.detail') }}</x-lentera.menu-item>
                                        <x-lentera.menu-item :href="route('prescriptions.precal', $rx)">{{ __('ui.precal.title') }}</x-lentera.menu-item>
                                    </x-lentera.row-actions>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><x-lentera.empty-state>{{ __('ui.customers.no_prescriptions') }}</x-lentera.empty-state></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <p class="text-sm text-gray-400">{{ __('ui.customers.transactions_soon') }}</p>
        </div>
    </div>
</x-app-layout>
