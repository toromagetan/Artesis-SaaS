<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.opname.title') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-lg border border-lentera-200 bg-lentera-50 px-4 py-3 text-sm text-lentera-800">{{ session('status') }}</div>
            @endif
            <div class="mb-4 flex items-center justify-end">
                <x-lentera.aksi-bar>
                    <x-lentera.menu-item :href="route('opnames.create')" primary>{{ __('ui.opname.create') }}</x-lentera.menu-item>
                </x-lentera.aksi-bar>
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.opname.date') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.opname.branch') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.opname.status') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.opname.items') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.opname.differences') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.opname.created_by') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($opnames as $opname)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 font-medium"><a href="{{ route('opnames.show', $opname) }}" class="text-lentera-800 underline-offset-2 hover:underline">{{ $opname->scheduled_date->format('d/m/Y') }}</a></td>
                                <td class="px-4 py-3">{{ $opname->branch->name }}</td>
                                <td class="px-4 py-3"><span class="inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-semibold {{ $opname->status === 'approved' ? 'border-lentera-200 bg-lentera-50 text-lentera-700' : 'border-gray-200 bg-gray-100 text-gray-500' }}">{{ __('ui.opname.statuses.'.$opname->status) }}</span></td>
                                <td class="px-4 py-3 tabular-nums">{{ $opname->items_count }}</td>
                                <td class="px-4 py-3 tabular-nums">{{ $opname->items->where('difference', '!=', 0)->count() }}</td>
                                <td class="px-4 py-3">{{ $opname->creator->name }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><x-lentera.empty-state>{{ __('ui.opname.empty') }}</x-lentera.empty-state></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $opnames->links() }}</div>
        </div>
    </div>
</x-app-layout>
