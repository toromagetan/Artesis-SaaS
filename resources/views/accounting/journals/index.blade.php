<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.accounting.journals') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="mb-4 flex items-center justify-between">
                <form method="GET" class="flex gap-2">
                    <select name="sumber" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                        <option value="">{{ __('ui.accounting.source') }}</option>
                        @foreach ($sources as $s)
                            <option value="{{ $s }}" @selected($source === $s)>{{ $s }}</option>
                        @endforeach
                    </select>
                    <x-text-input name="periode" value="{{ $period }}" placeholder="YYYY-MM" class="w-28" />
                    <x-secondary-button type="submit">{{ __('ui.search') }}</x-secondary-button>
                </form>
                @can('accounts.view')
                    <a href="{{ route('accounts.index') }}" class="text-sm text-lentera-800 underline">{{ __('ui.accounting.accounts') }}</a>
                @endcan
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.accounting.number') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.accounting.date') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.accounting.source') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.accounting.description') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ __('ui.accounting.total') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($journals as $journal)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 font-medium"><a href="{{ route('journals.show', $journal) }}" class="text-lentera-800 underline-offset-2 hover:underline">{{ $journal->number }}</a></td>
                                <td class="px-4 py-3">{{ $journal->journal_date->format('d/m/Y') }}</td>
                                <td class="px-4 py-3">{{ $journal->source_type }}@if ($journal->source_id) #{{ $journal->source_id }}@endif</td>
                                <td class="px-4 py-3">{{ $journal->description }}</td>
                                <td class="px-4 py-3 text-right tnum">{{ \App\Support\Money::fromDecimal((string) ($journal->total_debit ?? '0'))->format() }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><x-lentera.empty-state>{{ __('ui.accounting.empty') }}</x-lentera.empty-state></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $journals->links() }}</div>
        </div>
    </div>
</x-app-layout>
