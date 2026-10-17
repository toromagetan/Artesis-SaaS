<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.lab.title') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <form method="GET" class="mb-4 flex gap-2">
                <x-text-input name="q" :value="$q" :placeholder="__('ui.search')" class="w-64" />
                <select name="status" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                    <option value="">{{ __('ui.lab.status') }}</option>
                    @foreach (\App\Modules\Lab\Models\LensProcess::STATUSES as $s)
                        <option value="{{ $s }}" @selected($status === $s)>{{ __('ui.lab.statuses.'.$s) }}</option>
                    @endforeach
                </select>
                <x-secondary-button type="submit">{{ __('ui.search') }}</x-secondary-button>
            </form>

            <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.lab.sale_number') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.lab.customer') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.lab.date') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.lab.status') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.lab.technician') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.lab.estimate') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.lab.duration') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($processes as $process)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 font-medium">
                                    <a href="{{ route('lab.show', $process) }}" class="text-lentera-800 underline-offset-2 hover:underline">{{ $process->sale->number }}</a>
                                    <span class="block text-xs text-gray-400">{{ $process->sale->branch->name }}</span>
                                </td>
                                <td class="px-4 py-3">{{ $process->sale->customer->name }}</td>
                                <td class="px-4 py-3">{{ $process->sale->sale_date->format('d/m/Y') }}</td>
                                <td class="px-4 py-3"><x-lentera.badge>{{ __('ui.lab.statuses.'.$process->status) }}</x-lentera.badge></td>
                                <td class="px-4 py-3">{{ $process->technician?->name ?? __('ui.lab.unassigned') }}</td>
                                <td class="px-4 py-3 tnum">{{ $process->estimated_days ?? '—' }}</td>
                                <td class="px-4 py-3 tnum">{{ $process->durationDays() }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><x-lentera.empty-state>{{ __('ui.lab.empty') }}</x-lentera.empty-state></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $processes->links() }}</div>
        </div>
    </div>
</x-app-layout>
