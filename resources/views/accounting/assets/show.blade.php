<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ $asset->name }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
            <div class="mb-4 flex items-center justify-between">
                <a href="{{ route('assets.index') }}" class="text-sm text-gray-600 underline">{{ __('ui.back') }}</a>
                @if ($asset->status === 'active')
                    <x-lentera.aksi-bar>
                        <form method="POST" action="{{ route('assets.dispose', $asset) }}" onsubmit="return confirm('{{ __('ui.assets.dispose_confirm') }}')">
                            @csrf
                            <button type="submit" class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-red-300 bg-white px-3.5 py-2 text-sm font-medium text-red-600 shadow-soft transition duration-150 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-lentera-500">{{ __('ui.assets.dispose') }}</button>
                        </form>
                    </x-lentera.aksi-bar>
                @endif
            </div>

            <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-lg bg-white p-4 shadow-soft">
                    <div class="text-xs text-gray-500">{{ __('ui.assets.cost') }}</div>
                    <div class="mt-1 text-base font-semibold tnum">{{ $asset->cost()->format() }}</div>
                </div>
                <div class="rounded-lg bg-white p-4 shadow-soft">
                    <div class="text-xs text-gray-500">{{ __('ui.assets.monthly') }}</div>
                    <div class="mt-1 text-base font-semibold tnum">{{ $monthly->format() }}</div>
                </div>
                <div class="rounded-lg bg-white p-4 shadow-soft">
                    <div class="text-xs text-gray-500">{{ __('ui.assets.accumulated') }}</div>
                    <div class="mt-1 text-base font-semibold tnum">{{ $asset->accumulated()->format() }}</div>
                </div>
                <div class="rounded-lg bg-white p-4 shadow-soft">
                    <div class="text-xs text-gray-500">{{ __('ui.assets.book_value') }}</div>
                    <div class="mt-1 text-base font-semibold tnum">{{ $asset->bookValue()->format() }}</div>
                </div>
            </div>

            <div class="mb-4 rounded-lg bg-white p-6 shadow-soft">
                <dl class="grid gap-3 text-sm sm:grid-cols-2">
                    <div><dt class="text-gray-500">{{ __('ui.assets.acquired_at') }}</dt><dd class="font-medium">{{ $asset->acquired_at->format('d/m/Y') }}</dd></div>
                    <div><dt class="text-gray-500">{{ __('ui.assets.life_months') }}</dt><dd class="font-medium tnum">{{ $asset->useful_life_months }}</dd></div>
                    <div><dt class="text-gray-500">{{ __('ui.assets.method') }}</dt><dd class="font-medium">{{ __('ui.assets.straight_line') }}</dd></div>
                    <div><dt class="text-gray-500">{{ __('ui.assets.status') }}</dt><dd class="font-medium">{{ $asset->status === 'active' ? __('ui.assets.active') : __('ui.assets.disposed') }}</dd></div>
                </dl>
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                <div class="border-b border-gray-100 px-4 py-3 font-semibold text-gray-800">{{ __('ui.assets.depreciation_journals') }}</div>
                <table class="min-w-full text-sm">
                    <tbody>
                        @forelse ($journals as $journal)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-2.5 font-medium">
                                    <a href="{{ route('journals.show', $journal) }}" class="text-lentera-800 underline-offset-2 hover:underline tnum">{{ $journal->number }}</a>
                                </td>
                                <td class="px-4 py-2.5">{{ $journal->journal_date->format('d/m/Y') }}</td>
                                <td class="px-4 py-2.5 text-gray-600">{{ $journal->description }}</td>
                            </tr>
                        @empty
                            <tr><td class="px-4 py-3 text-gray-500">{{ __('ui.assets.no_journals') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
