<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.dashboard.title') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            @if (empty($cards))
                <div class="rounded-lg bg-white shadow-soft"><x-lentera.empty-state>{{ __('ui.dashboard.empty') }}</x-lentera.empty-state></div>
            @endif

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @isset($cards['sales'])
                    <a href="{{ route('sales.index') }}" class="rounded-lg bg-white p-6 shadow-soft transition duration-150 hover:shadow">
                        <p class="text-sm text-gray-500">{{ __('ui.dashboard.sales_today') }}</p>
                        <p class="mt-1 text-2xl font-semibold tnum">{{ $cards['sales']['today_total']->format() }}</p>
                        <p class="text-sm text-gray-400">{{ $cards['sales']['today_count'] }} {{ __('ui.dashboard.transactions') }}</p>
                        <p class="mt-3 text-sm text-gray-500">{{ __('ui.dashboard.receivable') }}: <span class="font-medium tnum">{{ $cards['sales']['receivable']->format() }}</span></p>
                    </a>
                @endisset

                @isset($cards['lab'])
                    <a href="{{ route('lab.index') }}" class="rounded-lg bg-white p-6 shadow-soft transition duration-150 hover:shadow">
                        <p class="text-sm text-gray-500">{{ __('ui.dashboard.lab_queue') }}</p>
                        <p class="mt-1 text-2xl font-semibold tnum">{{ $cards['lab']['waiting'] + $cards['lab']['edging'] + $cards['lab']['qc'] + $cards['lab']['ready'] }}</p>
                        <p class="text-sm text-gray-400">
                            {{ __('ui.dashboard.lab_waiting') }} {{ $cards['lab']['waiting'] }} ·
                            {{ __('ui.dashboard.lab_edging') }} {{ $cards['lab']['edging'] }} ·
                            {{ __('ui.dashboard.lab_qc') }} {{ $cards['lab']['qc'] }} ·
                            {{ __('ui.dashboard.lab_ready') }} {{ $cards['lab']['ready'] }}
                        </p>
                    </a>
                @endisset

                @isset($cards['stock'])
                    <a href="{{ route('stock.index') }}" class="rounded-lg bg-white p-6 shadow-soft transition duration-150 hover:shadow">
                        <p class="text-sm text-gray-500">{{ __('ui.dashboard.stock_alerts') }}</p>
                        <p class="mt-1 text-2xl font-semibold tnum">{{ $cards['stock']['low'] + $cards['stock']['out'] + $cards['stock']['backorder'] }}</p>
                        <p class="text-sm text-gray-400">
                            {{ __('ui.dashboard.stock_low') }} {{ $cards['stock']['low'] }} ·
                            {{ __('ui.dashboard.stock_out') }} {{ $cards['stock']['out'] }} ·
                            {{ __('ui.dashboard.stock_backorder') }} {{ $cards['stock']['backorder'] }}
                        </p>
                        <p class="mt-3 text-sm text-gray-500">{{ __('ui.dashboard.transfers_in') }}: <span class="font-medium tnum">{{ $cards['transfers_in'] }}</span></p>
                    </a>
                @endisset

                @isset($cards['pr_pending'])
                    <a href="{{ route('pr.index') }}" class="rounded-lg bg-white p-6 shadow-soft transition duration-150 hover:shadow">
                        <p class="text-sm text-gray-500">{{ __('ui.dashboard.pr_pending') }}</p>
                        <p class="mt-1 text-2xl font-semibold tnum">{{ $cards['pr_pending'] }}</p>
                    </a>
                @endisset

                @isset($cards['payables'])
                    <a href="{{ route('reports.payables') }}" class="rounded-lg bg-white p-6 shadow-soft transition duration-150 hover:shadow">
                        <p class="text-sm text-gray-500">{{ __('ui.dashboard.payables') }}</p>
                        <p class="mt-1 text-2xl font-semibold tnum">{{ $cards['payables']->format() }}</p>
                    </a>
                @endisset
            </div>
        </div>
    </div>
</x-app-layout>
