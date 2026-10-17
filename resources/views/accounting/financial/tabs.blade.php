{{-- Tab silang laporan keuangan (navigasi murni). --}}
<div class="mb-4 flex flex-wrap gap-2 text-sm">
    <a href="{{ route('financial.balance-sheet') }}" class="rounded-lg px-3 py-1.5 {{ request()->routeIs('financial.balance-sheet') ? 'bg-lentera-700 text-white' : 'bg-white text-gray-600 shadow-soft hover:text-lentera-800' }}">{{ __('ui.financial.balance_sheet') }}</a>
    <a href="{{ route('financial.income') }}" class="rounded-lg px-3 py-1.5 {{ request()->routeIs('financial.income') ? 'bg-lentera-700 text-white' : 'bg-white text-gray-600 shadow-soft hover:text-lentera-800' }}">{{ __('ui.financial.income_statement') }}</a>
    <a href="{{ route('financial.cash-flow') }}" class="rounded-lg px-3 py-1.5 {{ request()->routeIs('financial.cash-flow') ? 'bg-lentera-700 text-white' : 'bg-white text-gray-600 shadow-soft hover:text-lentera-800' }}">{{ __('ui.financial.cash_flow') }}</a>
</div>
