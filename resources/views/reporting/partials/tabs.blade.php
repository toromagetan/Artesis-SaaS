{{-- Tab silang antar-laporan (navigasi murni, bukan tombol aksi). --}}
<div class="mb-4 flex flex-wrap gap-2 text-sm">
    @can('reports.sales')
        <a href="{{ route('reports.sales') }}" class="rounded-lg px-3 py-1.5 {{ request()->routeIs('reports.sales') ? 'bg-lentera-700 text-white' : 'bg-white text-gray-600 shadow-soft hover:text-lentera-800' }}">{{ __('ui.reports.sales') }}</a>
    @endcan
    @can('reports.purchases')
        <a href="{{ route('reports.purchases') }}" class="rounded-lg px-3 py-1.5 {{ request()->routeIs('reports.purchases') ? 'bg-lentera-700 text-white' : 'bg-white text-gray-600 shadow-soft hover:text-lentera-800' }}">{{ __('ui.reports.purchases') }}</a>
        <a href="{{ route('reports.payables') }}" class="rounded-lg px-3 py-1.5 {{ request()->routeIs('reports.payables') ? 'bg-lentera-700 text-white' : 'bg-white text-gray-600 shadow-soft hover:text-lentera-800' }}">{{ __('ui.reports.payables') }}</a>
    @endcan
    @can('reports.stock')
        <a href="{{ route('reports.stock') }}" class="rounded-lg px-3 py-1.5 {{ request()->routeIs('reports.stock') ? 'bg-lentera-700 text-white' : 'bg-white text-gray-600 shadow-soft hover:text-lentera-800' }}">{{ __('ui.reports.stock') }}</a>
    @endcan
</div>
