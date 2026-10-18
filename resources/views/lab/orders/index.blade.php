<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.lab_orders.title') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-6xl sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-lg border border-lentera-200 bg-lentera-50 px-4 py-3 text-sm text-lentera-800">{{ session('status') }}</div>
            @endif

            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <form method="GET" action="{{ route('lab-orders.index') }}" class="flex items-center gap-2">
                    <select name="status" onchange="this.form.submit()" class="rounded-lg border-gray-300 text-sm focus:border-lentera-500 focus:ring-lentera-500">
                        <option value="">{{ __('ui.lab.status') }}: —</option>
                        @foreach (\App\Modules\Lab\Models\LabOrder::STATUSES as $s)
                            <option value="{{ $s }}" @selected($status === $s)>{{ __('ui.lab_orders.statuses.'.$s) }}</option>
                        @endforeach
                    </select>
                </form>
                <x-lentera.dropdown-aksi>
                    <x-lentera.menu-item :href="route('lab-orders.create')">{{ __('ui.lab_orders.create') }}</x-lentera.menu-item>
                </x-lentera.dropdown-aksi>
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.lab_orders.number') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.lab_orders.sale') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.lab_orders.lab_name') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.lab_orders.sent_at') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.lab.status') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ __('ui.lab_orders.cost') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.lab_orders.received_at') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($orders as $order)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 font-medium">
                                    <a href="{{ route('lab-orders.show', $order) }}" class="text-lentera-800 underline-offset-2 hover:underline">{{ $order->number }}</a>
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ $order->sale->number }} · {{ $order->sale->customer->name }}</td>
                                <td class="px-4 py-3 text-gray-800">{{ $order->lab_name }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $order->sent_at?->format('d/m/Y') ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    @if ($order->status === 'received')
                                        <span class="inline-block rounded-full border border-lentera-200 bg-lentera-50 px-2.5 py-0.5 text-[11px] font-semibold text-lentera-700">{{ __('ui.lab_orders.statuses.received') }}</span>
                                    @elseif ($order->status === 'ordered')
                                        <span class="inline-block rounded-full border border-gold/50 bg-gold/10 px-2.5 py-0.5 text-[11px] font-semibold text-gold-dark">{{ __('ui.lab_orders.statuses.ordered') }}</span>
                                    @else
                                        <span class="inline-block rounded-full border border-gray-200 bg-gray-50 px-2.5 py-0.5 text-[11px] font-semibold text-gray-500">{{ __('ui.lab_orders.statuses.waiting') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right tnum">{{ \App\Support\Money::fromDecimal((string) $order->lab_cost)->format() }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $order->received_at?->format('d/m/Y') ?? '—' }}</td>
                                <td class="px-4 py-3 text-right">
                                    <x-lentera.row-actions>
                                        <x-lentera.menu-item :href="route('lab-orders.show', $order)">{{ __('ui.lab_orders.detail') }}</x-lentera.menu-item>
                                        @if ($order->status !== 'received')
                                            <div class="my-1 border-t border-gray-100"></div>
                                            <form method="POST" action="{{ route('lab-orders.transition', $order) }}">
                                                @csrf
                                                <input type="hidden" name="to" value="{{ $order->status === 'waiting' ? 'ordered' : 'received' }}">
                                                <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-gray-700 transition duration-150 hover:bg-lentera-50 hover:text-lentera-900">{{ __('ui.lab_orders.advance') }}: {{ __('ui.lab_orders.statuses.'.($order->status === 'waiting' ? 'ordered' : 'received')) }}</button>
                                            </form>
                                        @endif
                                    </x-lentera.row-actions>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-6"><x-lentera.empty-state :message="__('ui.lab_orders.empty')" /></td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $orders->links() }}</div>
        </div>
    </div>
</x-app-layout>
