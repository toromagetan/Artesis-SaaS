<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h1 class="text-xl font-bold text-lentera-950">{{ __('ui.tenants.title') }}</h1>
            <a href="{{ route('platform.tenants.create') }}" class="rounded-lg bg-lentera-800 px-4 py-2 text-sm font-semibold text-white hover:bg-lentera-700">{{ __('ui.tenants.create') }}</a>
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        @if (session('status'))
            <div class="mb-4 rounded-lg bg-lentera-50 px-4 py-3 text-sm font-medium text-lentera-900 ring-1 ring-lentera-200">{{ session('status') }}</div>
        @endif

        <div class="overflow-hidden rounded-2xl bg-white shadow-soft ring-1 ring-black/5">
            <table class="min-w-full divide-y divide-gray-100">
                <thead class="bg-lentera-50/60">
                    <tr class="text-left text-xs font-semibold uppercase tracking-wide text-lentera-900">
                        <th class="px-5 py-3">{{ __('ui.tenants.name') }}</th>
                        <th class="px-5 py-3">{{ __('ui.tenants.plan') }}</th>
                        <th class="px-5 py-3">{{ __('ui.tenants.branches') }}</th>
                        <th class="px-5 py-3">{{ __('ui.tenants.users') }}</th>
                        <th class="px-5 py-3">{{ __('ui.tenants.status') }}</th>
                        <th class="px-5 py-3 text-right">{{ __('ui.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse ($tenants as $tenant)
                        @php $current = $tenant->currentSubscription(); @endphp
                        <tr class="text-sm">
                            <td class="px-5 py-3">
                                <a href="{{ route('platform.tenants.show', $tenant) }}" class="font-semibold text-lentera-900 hover:underline">{{ $tenant->name }}</a>
                                <span class="block text-xs text-gray-500">{{ $tenant->slug }}</span>
                            </td>
                            <td class="px-5 py-3">
                                @if ($current)
                                    {{ $current->plan->name }}
                                    <span class="block text-xs text-gray-500">{{ __('ui.tenants.expires') }}: {{ $current->expires_at?->format('d/m/Y') ?? __('ui.tenants.never') }}</span>
                                @else
                                    <span class="text-gray-400">{{ __('ui.tenants.no_plan') }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3">{{ $tenant->branches_count }}</td>
                            <td class="px-5 py-3">{{ $tenant->users_count }}</td>
                            <td class="px-5 py-3">
                                @if ($tenant->is_active)
                                    <span class="rounded-full bg-lentera-100 px-2 py-0.5 text-xs font-semibold text-lentera-900">{{ __('ui.tenants.active') }}</span>
                                @else
                                    <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-danger">{{ __('ui.tenants.inactive') }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex items-center justify-end gap-2 whitespace-nowrap">
                                    <a href="{{ route('platform.tenants.show', $tenant) }}" class="rounded-lg bg-white px-3 py-1.5 text-xs font-semibold text-lentera-900 ring-1 ring-lentera-200 hover:bg-lentera-50">{{ __('ui.tenants.detail') }}</a>
                                    <form method="POST" action="{{ route('platform.tenants.toggle', $tenant) }}">
                                        @csrf
                                        <button type="submit" class="rounded-lg px-3 py-1.5 text-xs font-semibold ring-1 {{ $tenant->is_active ? 'bg-white text-danger ring-red-200 hover:bg-red-50' : 'bg-lentera-800 text-white ring-lentera-800 hover:bg-lentera-700' }}">{{ $tenant->is_active ? __('ui.deactivate') : __('ui.activate') }}</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-8 text-center text-sm text-gray-500">{{ __('ui.tenants.empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
