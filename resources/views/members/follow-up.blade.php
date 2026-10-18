<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.members.follow_up_title') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <p class="mb-4 text-sm text-gray-600">{{ __('ui.members.follow_up_hint') }}</p>
            <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.members.customer') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.members.sale_number') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.members.branch') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.members.completed_at') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.members.wa_sent') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($processes as $process)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 font-medium text-gray-800">
                                    @if ($process->sale?->customer)
                                        <a href="{{ route('customers.show', $process->sale->customer) }}" class="text-lentera-800 underline-offset-2 hover:underline">{{ $process->sale->customer->name }}</a>
                                    @else — @endif
                                </td>
                                <td class="px-4 py-3 tnum">
                                    @if ($process->sale)
                                        <a href="{{ route('sales.show', $process->sale) }}" class="text-lentera-800 underline-offset-2 hover:underline">{{ $process->sale->number }}</a>
                                    @else — @endif
                                </td>
                                <td class="px-4 py-3">{{ $process->sale?->branch?->name ?? '—' }}</td>
                                <td class="px-4 py-3">{{ $process->completed_at?->format('d/m/Y') }}</td>
                                <td class="px-4 py-3">
                                    @if ($process->wa_sent)
                                        <span class="rounded-full bg-lentera-100 px-2.5 py-0.5 text-xs font-medium text-lentera-800">{{ __('ui.members.wa_sent_yes') }}</span>
                                    @else
                                        <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600">{{ __('ui.members.wa_sent_no') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><x-lentera.empty-state>{{ __('ui.members.follow_up_empty') }}</x-lentera.empty-state></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $processes->links() }}</div>
        </div>
    </div>
</x-app-layout>
