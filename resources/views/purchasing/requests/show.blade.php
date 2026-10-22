<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ $pr->number }}</h1>
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
                <a href="{{ route('pr.index') }}" class="text-sm text-gray-600 underline">{{ __('ui.back') }}</a>
                <x-lentera.aksi-bar>
                    <x-lentera.menu-item :href="route('pr.print', $pr)" target="_blank" print>{{ __('ui.print') }}</x-lentera.menu-item>
                    @can('pr.create')
                        <x-lentera.menu-item :href="route('pr.create')">{{ __('ui.purchasing.pr_create') }}</x-lentera.menu-item>
                    @endcan
                    @if ($pr->status === 'draft')
                        @can('pr.create')
                            <form method="POST" action="{{ route('pr.submit', $pr) }}">
                                @csrf
                                <button type="submit" class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-sm font-medium text-gray-700 shadow-soft transition duration-150 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-lentera-500">{{ __('ui.purchasing.submit') }}</button>
                            </form>
                        @endcan
                    @endif
                    @if ($pr->status === 'submitted')
                        @can('pr.approve')
                            <form method="POST" action="{{ route('pr.approve', $pr) }}">
                                @csrf
                                <button type="submit" class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-sm font-medium text-gray-700 shadow-soft transition duration-150 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-lentera-500">{{ __('ui.purchasing.approve') }}</button>
                            </form>
                        @endcan
                    @endif
                    @if (in_array($pr->status, ['draft', 'submitted'], true))
                        @can('pr.cancel')

                            <form method="POST" action="{{ route('pr.cancel', $pr) }}" onsubmit="return confirm('{{ __('ui.purchasing.cancel') }}?')">
                                @csrf
                                <button type="submit" class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-red-300 bg-white px-3.5 py-2 text-sm font-medium text-red-600 shadow-soft transition duration-150 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-lentera-500">{{ __('ui.purchasing.cancel') }}</button>
                            </form>
                        @endcan
                    @endif
                </x-lentera.aksi-bar>
            </div>

            <div class="mb-6 rounded-lg bg-white p-6 shadow-soft">
                <dl class="grid grid-cols-[160px_1fr] gap-y-3 text-sm">
                    <dt class="text-gray-500">{{ __('ui.purchasing.requester') }}</dt>
                    <dd class="font-medium text-gray-800">{{ $pr->requester->name }}</dd>
                    <dt class="text-gray-500">{{ __('ui.purchasing.branch') }}</dt>
                    <dd class="font-medium text-gray-800">{{ $pr->branch->name }}</dd>
                    <dt class="text-gray-500">{{ __('ui.purchasing.date') }}</dt>
                    <dd class="font-medium text-gray-800">{{ $pr->created_at->format('d/m/Y') }}</dd>
                    <dt class="text-gray-500">{{ __('ui.purchasing.status') }}</dt>
                    <dd class="font-medium text-gray-800">{{ __('ui.purchasing.pr_statuses.'.$pr->status) }}</dd>
                    @if ($pr->notes)
                        <dt class="text-gray-500">{{ __('ui.purchasing.notes') }}</dt>
                        <dd class="font-medium text-gray-800">{{ $pr->notes }}</dd>
                    @endif
                </dl>
            </div>

            <h2 class="mb-2 text-base font-semibold text-gray-800">{{ __('ui.purchasing.items') }}</h2>
            <div class="mb-6 overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.purchasing.product') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.purchasing.qty') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.purchasing.estimated_price') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.purchasing.supplier') }}</th>
                            <th class="px-4 py-3 font-medium">PO</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pr->items as $item)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 font-medium text-gray-800">{{ $item->product->name }}
                                    @if ($item->rx_parameters)
                                        <span class="mt-1 block text-xs font-normal text-gray-500">
                                            {{ __('ui.sales.precal') }}:
                                            @foreach (\App\Modules\Sales\Services\RxPrecalValidator::KEYS as $key)
                                                @if (($item->rx_parameters['precal'][$key] ?? null) !== null){{ strtoupper($key) }} {{ $item->rx_parameters['precal'][$key] }}@if (! $loop->last) · @endif @endif
                                            @endforeach
                                            ({{ ($item->rx_parameters['precal_complete'] ?? false) ? __('ui.sales.precal_complete') : __('ui.sales.precal_incomplete') }})
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 tnum">{{ $item->qty }}</td>
                                <td class="px-4 py-3 tnum">{{ $item->estimated_price !== null ? \App\Support\Money::fromDecimal((string) $item->estimated_price)->format() : '—' }}</td>
                                <td class="px-4 py-3">{{ $item->supplier?->name ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    @if ($item->purchaseOrder)
                                        <a class="text-lentera-800 underline-offset-2 hover:underline" href="{{ route('po.show', $item->purchaseOrder) }}">{{ $item->purchaseOrder->number }}</a>
                                    @else — @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($pr->status === 'approved')
                @can('pr.approve')
                    <h2 class="mb-2 text-base font-semibold text-gray-800">{{ __('ui.purchasing.convert') }}</h2>
                    <div class="rounded-lg bg-white p-6 shadow-soft">
                        <form method="POST" action="{{ route('pr.convert', $pr) }}" class="flex flex-wrap items-end gap-4">
                            @csrf
                            <div>
                                <x-input-label for="supplier_id" :value="__('ui.purchasing.supplier')" />
                                <select id="supplier_id" name="supplier_id" class="mt-1 block w-64 rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500" required>
                                    @foreach ($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <x-input-label for="order_date" :value="__('ui.purchasing.date')" />
                                <x-text-input id="order_date" name="order_date" type="date" class="mt-1 block" :value="now()->toDateString()" />
                            </div>
                            <x-primary-button>{{ __('ui.purchasing.convert') }}</x-primary-button>
                        </form>
                    </div>
                @endcan
            @endif
        </div>
    </div>
</x-app-layout>
