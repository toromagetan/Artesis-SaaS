@if (!empty($ops_warnings))
    <div class="mb-4 space-y-2">
        @foreach ($ops_warnings as $warning)
            <div class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800">
                {{ __('ui.dashboard.ops_'.$warning['key'], $warning['params']) }}
            </div>
        @endforeach
    </div>
@endif

@if (empty($cards))
    <div class="rounded-lg bg-white shadow-soft"><x-lentera.empty-state>{{ __('ui.dashboard.empty') }}</x-lentera.empty-state></div>
@endif

<div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
    @isset($cards['sales'])
        <a href="{{ route('sales.index') }}" class="rounded-lg bg-white p-6 shadow-soft transition duration-150 hover:shadow">
            <p class="text-sm text-gray-500">{{ __('ui.dashboard.sales_period') }}</p>
            <p class="mt-1 text-2xl font-semibold tnum">{{ $cards['sales']['total']->format() }}</p>
            <p class="text-sm text-gray-400">{{ $cards['sales']['count'] }} {{ __('ui.dashboard.transactions') }}</p>
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
        @if (! empty($cards['stock']['expiring']))
            <div class="rounded-lg border border-gold/50 bg-gold/10 p-6">
                <p class="text-sm font-medium text-gold-dark">{{ __('ui.dashboard.expiring_title') }}</p>
                <ul class="mt-2 space-y-1 text-sm text-gold-dark">
                    @foreach (array_slice($cards['stock']['expiring'], 0, 5) as $row)
                        <li class="flex items-center justify-between gap-2">
                            <span>{{ $row['product_name'] }} · {{ $row['branch_name'] }}</span>
                            <span class="font-medium tnum">{{ $row['qty'] }} · {{ \Illuminate\Support\Carbon::parse($row['expiry_date'])->format('d/m/Y') }}@if ($row['expired']) ({{ __('ui.dashboard.expired') }})@endif</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
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

    @isset($cards['finance'])
        <div class="rounded-lg bg-white p-6 shadow-soft">
            <p class="text-sm text-gray-500">{{ __('ui.dashboard.finance_period') }}</p>
            <p class="mt-1 text-2xl font-semibold tnum">{{ $cards['finance']['net']->format() }}</p>
            <p class="text-sm text-gray-400">{{ __('ui.dashboard.period_net') }}</p>
            @if ($cards['finance']['cash'] !== null)
                <p class="mt-3 text-sm text-gray-500">{{ __('ui.dashboard.cash_position') }}: <span class="font-medium tnum">{{ $cards['finance']['cash']->format() }}</span></p>
            @endif
            @if (! empty($cards['finance']['by_branch']))
                <ul class="mt-3 space-y-1 border-t border-gray-50 pt-3 text-sm">
                    @foreach ($cards['finance']['by_branch'] as $row)
                        <li class="flex items-center justify-between">
                            <span class="text-gray-500">{{ $row['branch_id'] === null ? __('ui.financial.no_branch') : ($cards['finance']['branch_names'][$row['branch_id']] ?? '#'.$row['branch_id']) }}</span>
                            <span class="font-medium tnum">{{ $row['net']->format() }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
            <p class="mt-3"><a href="{{ route('financial.income') }}" class="text-sm text-lentera-800 underline">{{ __('ui.financial.income_statement') }}</a></p>
        </div>
    @endisset
</div>
