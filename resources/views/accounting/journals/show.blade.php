<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ $journal->number }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-lg border border-lentera-200 bg-lentera-50 px-4 py-3 text-sm text-lentera-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            <div class="mb-4 flex items-center justify-between">
                <a href="{{ route('journals.index') }}" class="text-sm text-gray-600 underline">{{ __('ui.back') }}</a>
                @if ($reversal === null && $journal->source_type !== 'reversal')
                    @can('journals.reverse')
                        <x-lentera.dropdown-aksi>
                            <form method="POST" action="{{ route('journals.reverse', $journal) }}" class="px-4 py-2">
                                @csrf
                                <label class="mb-1 block text-xs text-gray-500" for="reason">{{ __('ui.accounting.reverse_reason') }}</label>
                                <input id="reason" name="reason" required class="mb-2 block w-56 rounded-md border-gray-300 text-sm shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                                <button type="submit" class="block w-full rounded-md bg-red-50 px-3 py-1.5 text-left text-sm text-red-600 transition duration-150 hover:bg-red-100">{{ __('ui.accounting.reverse') }}</button>
                            </form>
                        </x-lentera.dropdown-aksi>
                    @endcan
                @endif
            </div>

            <div class="mb-6 rounded-lg bg-white p-6 shadow-soft">
                <dl class="grid grid-cols-[160px_1fr] gap-y-3 text-sm">
                    <dt class="text-gray-500">{{ __('ui.accounting.date') }}</dt>
                    <dd class="font-medium text-gray-800">{{ $journal->journal_date->format('d/m/Y') }}</dd>
                    <dt class="text-gray-500">{{ __('ui.accounting.source') }}</dt>
                    <dd class="font-medium text-gray-800">{{ $journal->source_type }}@if ($journal->source_id) #{{ $journal->source_id }}@endif</dd>
                    <dt class="text-gray-500">{{ __('ui.accounting.description') }}</dt>
                    <dd class="font-medium text-gray-800">{{ $journal->description }}</dd>
                    @if ($journal->reversalOf)
                        <dt class="text-gray-500">{{ __('ui.accounting.reversal_of') }}</dt>
                        <dd class="font-medium text-gray-800"><a class="text-lentera-800 underline-offset-2 hover:underline" href="{{ route('journals.show', $journal->reversalOf) }}">{{ $journal->reversalOf->number }}</a></dd>
                    @endif
                    @if ($reversal !== null)
                        <dt class="text-gray-500">{{ __('ui.accounting.reversed') }}</dt>
                        <dd class="font-medium text-gray-800"><a class="text-lentera-800 underline-offset-2 hover:underline" href="{{ route('journals.show', $reversal) }}">{{ $reversal->number }}</a></dd>
                    @endif
                </dl>
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.accounting.account') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.stock.branch') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ __('ui.accounting.debit') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ __('ui.accounting.credit') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($journal->lines as $line)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 font-medium text-gray-800">{{ $line->account->code }} — {{ $line->account->name }}</td>
                                <td class="px-4 py-3">{{ $line->branch?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-right tnum">{{ \App\Support\Money::fromDecimal((string) $line->debit)->format() }}</td>
                                <td class="px-4 py-3 text-right tnum">{{ \App\Support\Money::fromDecimal((string) $line->credit)->format() }}</td>
                            </tr>
                        @endforeach
                        <tr class="bg-gray-50 font-medium">
                            <td class="px-4 py-3" colspan="2">{{ __('ui.accounting.total') }}</td>
                            <td class="px-4 py-3 text-right tnum">{{ $journal->totalDebit()->format() }}</td>
                            <td class="px-4 py-3 text-right tnum">{{ $journal->totalDebit()->format() }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
