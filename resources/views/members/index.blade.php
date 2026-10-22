<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.members.title') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-lg border border-lentera-200 bg-lentera-50 px-4 py-3 text-sm text-lentera-800">{{ session('status') }}</div>
            @endif
            @if (session('portal_url'))
                <div class="mb-4 rounded-lg border border-gold/50 bg-gold/10 px-4 py-3 text-sm text-gold-dark">
                    <p class="font-medium">{{ __('ui.members.portal_url_label') }}</p>
                    <p class="mt-1 break-all font-mono text-xs">{{ session('portal_url') }}</p>
                </div>
            @endif

            <div class="mb-4 flex items-center justify-between">
                <form method="GET" class="flex gap-2">
                    <x-text-input name="q" :value="$q" :placeholder="__('ui.members.search_placeholder')" class="w-72" />
                    <select name="level" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                        <option value="">{{ __('ui.members.level') }}</option>
                        @foreach ($levels as $level)
                            <option value="{{ $level->id }}" @selected((string) $levelId === (string) $level->id)>{{ $level->name }}</option>
                        @endforeach
                    </select>
                    <x-secondary-button type="submit">{{ __('ui.search') }}</x-secondary-button>
                </form>
                @can('members.manage')
                    <x-lentera.aksi-bar>
                        <x-lentera.menu-item :href="route('members.levels')">{{ __('ui.members.manage_levels') }}</x-lentera.menu-item>
                    </x-lentera.aksi-bar>
                @endcan
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.members.customer') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.members.level') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.members.points_balance') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($customers as $customer)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 font-medium text-gray-800">
                                    <a href="{{ route('customers.show', $customer) }}" class="text-lentera-800 underline-offset-2 hover:underline">{{ $customer->name }}</a>
                                    <div class="text-xs font-normal text-gray-400">{{ $customer->phone }}</div>
                                </td>
                                <td class="px-4 py-3">{{ $customer->level?->name ?? '—' }}</td>
                                <td class="px-4 py-3 tnum font-medium">{{ number_format((int) ($balances[$customer->id] ?? 0), 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right">
                                    <x-lentera.aksi-baris>
                                        @can('points.adjust')
                                            <x-lentera.menu-item :href="route('members.adjust', $customer)" sm>{{ __('ui.members.adjust_points') }}</x-lentera.menu-item>
                                        @endcan
                                        @can('members.manage')
                                            <form method="POST" action="{{ route('members.portal.issue', $customer) }}">
                                                @csrf
                                                <button type="submit" class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-[13px] font-medium text-gray-700 shadow-soft transition duration-150 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-lentera-500">{{ __('ui.members.portal_issue') }}</button>
                                            </form>
                                            <form method="POST" action="{{ route('members.portal.revoke', $customer) }}" onsubmit="return confirm('{{ __('ui.members.portal_revoked') }}?')">
                                                @csrf
                                                <button type="submit" class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-red-300 bg-white px-2.5 py-1.5 text-[13px] font-medium text-red-600 shadow-soft transition duration-150 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-lentera-500">{{ __('ui.members.portal_revoke') }}</button>
                                            </form>
                                        @endcan
                                        <x-lentera.menu-item :href="route('customers.show', $customer)" sm>{{ __('ui.members.view_customer') }}</x-lentera.menu-item>
                                    </x-lentera.aksi-baris>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4"><x-lentera.empty-state>{{ __('ui.members.empty') }}</x-lentera.empty-state></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $customers->links() }}</div>
        </div>
    </div>
</x-app-layout>
