<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ $doctor->name }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-lg border border-lentera-200 bg-lentera-50 px-4 py-3 text-sm text-lentera-800">{{ session('status') }}</div>
            @endif

            <div class="mb-4 flex items-center justify-between">
                <a href="{{ route('doctors.index') }}" class="text-sm text-gray-600 underline">{{ __('ui.back') }}</a>
                <x-lentera.aksi-bar>
                    <x-lentera.menu-item :href="route('doctors.edit', $doctor)">{{ __('ui.edit') }}</x-lentera.menu-item>

                    <form method="POST" action="{{ route('doctors.toggle-active', $doctor) }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-sm font-medium shadow-soft transition duration-150 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-lentera-500 {{ $doctor->is_active ? 'text-red-600' : 'text-lentera-700' }}">{{ $doctor->is_active ? __('ui.deactivate') : __('ui.activate') }}</button>
                    </form>
                </x-lentera.aksi-bar>
            </div>

            <div class="mb-6 rounded-lg bg-white p-6 shadow-soft">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ __('ui.doctors.clinic') }}</p>
                        <p class="mt-1 text-sm font-medium text-gray-800">{{ $doctor->clinic ?: '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ __('ui.doctors.phone') }}</p>
                        <p class="mt-1 text-sm font-medium text-gray-800">{{ $doctor->phone ?: '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ __('ui.doctors.commission_rate') }}</p>
                        <p class="mt-1 text-sm font-medium text-gray-800 tnum">{{ rtrim(rtrim(number_format((float) $doctor->commission_rate, 2, ',', '.'), '0'), ',') }}%</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ __('ui.doctors.status') }}</p>
                        <p class="mt-1"><x-lentera.badge :active="$doctor->is_active" /></p>
                    </div>
                </div>
            </div>

            <div class="mb-6 grid gap-4 sm:grid-cols-3">
                <div class="rounded-lg bg-white p-5 shadow-soft">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ __('ui.doctors.total_referrals') }}</p>
                    <p class="mt-1 text-xl font-semibold text-lentera-950 tnum">{{ $stats['referrals_count'] }}</p>
                </div>
                <div class="rounded-lg bg-white p-5 shadow-soft">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ __('ui.doctors.referrals_value') }}</p>
                    <p class="mt-1 text-xl font-semibold text-lentera-950 tnum">{{ \App\Support\Money::fromDecimal((string) $stats['referrals_total'])->format() }}</p>
                </div>
                <div class="rounded-lg bg-white p-5 shadow-soft">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ __('ui.doctors.total_commission') }}</p>
                    <p class="mt-1 text-xl font-semibold text-lentera-950 tnum">{{ \App\Support\Money::fromDecimal((string) $stats['commission_total'])->format() }}</p>
                </div>
            </div>

            <h2 class="mb-3 text-base font-semibold text-lentera-950">{{ __('ui.doctors.recent_sales') }}</h2>
            <div class="mb-8 overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.doctors.sale_number') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.doctors.customer') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.doctors.date') }}</th>
                            <th class="px-4 py-3 font-medium text-right">{{ __('ui.doctors.amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentSales as $sale)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 font-medium text-gray-800">
                                    <a href="{{ route('sales.show', $sale) }}" class="text-lentera-800 underline decoration-lentera-300 underline-offset-2 hover:text-lentera-950">{{ $sale->number }}</a>
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ $sale->customer?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $sale->sale_date?->format('d/m/Y') ?? $sale->created_at->format('d/m/Y') }}</td>
                                <td class="px-4 py-3 text-right tnum">{{ \App\Support\Money::fromDecimal((string) $sale->total_amount)->format() }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4"><x-lentera.empty-state>{{ __('ui.doctors.no_sales') }}</x-lentera.empty-state></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <h2 class="mb-3 text-base font-semibold text-lentera-950">{{ __('ui.doctors.recent_commissions') }}</h2>
            <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.doctors.sale_number') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.doctors.period') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.doctors.status') }}</th>
                            <th class="px-4 py-3 font-medium text-right">{{ __('ui.doctors.amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentCommissions as $commission)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 font-medium text-gray-800">{{ $commission->sale?->number ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $commission->period }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $commission->status }}</td>
                                <td class="px-4 py-3 text-right tnum">{{ \App\Support\Money::fromDecimal((string) $commission->amount)->format() }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4"><x-lentera.empty-state>{{ __('ui.doctors.no_commissions') }}</x-lentera.empty-state></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
