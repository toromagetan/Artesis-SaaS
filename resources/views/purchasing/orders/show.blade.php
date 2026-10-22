<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ $po->number }}</h1>
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
                <a href="{{ route('po.index') }}" class="text-sm text-gray-600 underline">{{ __('ui.back') }}</a>
                <div class="flex items-center gap-2">
                    <x-lentera.aksi-bar>
                        <x-lentera.menu-item :href="route('po.print', $po)" target="_blank" print>{{ __('ui.purchasing.po_print') }}</x-lentera.menu-item>
                    </x-lentera.aksi-bar>
                    @unless ($po->isVoid())
                        <x-lentera.aksi-bar>
                            @can('po.create')
                                <x-lentera.menu-item :href="route('po.create')">{{ __('ui.purchasing.po_create') }}</x-lentera.menu-item>
                            @endcan
                            @if ($po->status === 'draft')
                                @can('po.update')
                                    <form method="POST" action="{{ route('po.send', $po) }}">
                                        @csrf
                                        <button type="submit" class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-sm font-medium text-gray-700 shadow-soft transition duration-150 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-lentera-500">{{ __('ui.purchasing.send') }}</button>
                                    </form>
                                @endcan
                            @endif
                            @if (in_array($po->status, ['sent', 'partial'], true))
                                @can('receipts.create')
                                    <x-lentera.menu-item :href="route('po.receive', $po)">{{ __('ui.purchasing.receive') }}</x-lentera.menu-item>
                                @endcan
                            @endif
                            @can('po.void')
                                <x-lentera.aksi-dialog danger :label="__('ui.purchasing.void')" :title="__('ui.purchasing.void').'?'">
                                    <form method="POST" action="{{ route('po.void', $po) }}">
                                        @csrf
                                        <input type="text" name="reason" required maxlength="255" placeholder="{{ __('ui.purchasing.void_reason') }}" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-red-400 focus:ring-red-400">
                                        <div class="mt-4 flex justify-end gap-2">
                                            <button type="button" @click="open = false" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('ui.back') }}</button>
                                            <button type="submit" class="inline-flex items-center rounded-lg bg-red-600 px-3.5 py-2 text-sm font-semibold text-white hover:bg-red-700">{{ __('ui.purchasing.void') }}</button>
                                        </div>
                                    </form>
                                </x-lentera.aksi-dialog>
                            @endcan
                        </x-lentera.aksi-bar>
                    @endunless
                </div>
            </div>

            @if ($po->isVoid())
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ __('ui.purchasing.po_statuses.void') }} — {{ $po->void_reason }}
                </div>
            @endif

            <div class="mb-6 rounded-lg bg-white p-6 shadow-soft">
                <dl class="grid grid-cols-[160px_1fr] gap-y-3 text-sm">
                    <dt class="text-gray-500">{{ __('ui.purchasing.supplier') }}</dt>
                    <dd class="font-medium text-gray-800">{{ $po->supplier->name }}</dd>
                    <dt class="text-gray-500">{{ __('ui.purchasing.destination_branch') }}</dt>
                    <dd class="font-medium text-gray-800">{{ $po->branch->name }}</dd>
                    <dt class="text-gray-500">{{ __('ui.purchasing.date') }}</dt>
                    <dd class="font-medium text-gray-800">{{ $po->order_date->format('d/m/Y') }}</dd>
                    <dt class="text-gray-500">{{ __('ui.purchasing.status') }}</dt>
                    <dd class="font-medium text-gray-800">{{ __('ui.purchasing.po_statuses.'.$po->status) }}</dd>
                    <dt class="text-gray-500">{{ __('ui.purchasing.total') }}</dt>
                    <dd class="font-medium text-gray-800 tnum">{{ \App\Support\Money::fromDecimal((string) $po->total_amount)->format() }}</dd>
                </dl>
            </div>

            <h2 class="mb-2 text-base font-semibold text-gray-800">{{ __('ui.purchasing.items') }}</h2>
            <div class="mb-6 overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.purchasing.product') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.purchasing.qty_ordered') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.purchasing.qty_received') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.purchasing.qty_remaining') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.purchasing.unit_cost') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.purchasing.line_total') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($po->items as $item)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 font-medium text-gray-800">{{ $item->product->name }}</td>
                                <td class="px-4 py-3 tnum">{{ $item->qty_ordered }}</td>
                                <td class="px-4 py-3 tnum">{{ $item->qty_received }}</td>
                                <td class="px-4 py-3 tnum">{{ $item->remainingQty() }}</td>
                                <td class="px-4 py-3 tnum">{{ \App\Support\Money::fromDecimal((string) $item->unit_cost)->format() }}</td>
                                <td class="px-4 py-3 tnum">{{ \App\Support\Money::fromDecimal((string) $item->line_total)->format() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <h2 class="mb-2 text-base font-semibold text-gray-800">{{ __('ui.transfer.distribution') }}</h2>
            <div class="mb-6 rounded-lg bg-white p-6 shadow-soft">
                @can('stock.transfer')
                    <form method="POST" action="{{ route('po.transfer.store', $po) }}">
                        @csrf
                        <div class="space-y-2">
                            @foreach ($po->items as $i => $item)
                                <div class="grid grid-cols-12 items-end gap-2">
                                    <div class="col-span-7 text-sm">
                                        <span class="font-medium text-gray-800">{{ $item->product->name }}</span>
                                        <span class="block text-xs text-gray-400">{{ __('ui.transfer.available') }}: {{ $distributable[$item->product_id] ?? 0 }}</span>
                                    </div>
                                    <input type="hidden" name="items[{{ $i }}][product_id]" value="{{ $item->product_id }}">
                                    <div class="col-span-5">
                                        <x-text-input name="items[{{ $i }}][qty]" type="number" min="0" max="{{ $distributable[$item->product_id] ?? 0 }}" class="block w-full tnum" placeholder="Qty kirim" />
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-4 flex flex-wrap items-end gap-3">
                            <div>
                                <x-input-label for="tujuan" :value="__('ui.transfer.to')" />
                                <select id="tujuan" name="tujuan" class="mt-1 block w-64 rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500" required>
                                    @foreach ($branches as $branch)
                                        @unless ($branch->id === $po->branch_id)
                                            <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                        @endunless
                                    @endforeach
                                </select>
                            </div>
                            <x-primary-button>{{ __('ui.transfer.send') }}</x-primary-button>
                        </div>
                    </form>
                @endcan

                @if ($transfers->isNotEmpty())
                    <h3 class="mb-2 mt-6 text-sm font-semibold text-gray-700">{{ __('ui.transfer.distribution_history') }}</h3>
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 text-left text-gray-500">
                                <th class="py-2 pe-4 font-medium">{{ __('ui.transfer.number') }}</th>
                                <th class="py-2 pe-4 font-medium">{{ __('ui.transfer.to') }}</th>
                                <th class="py-2 pe-4 font-medium">{{ __('ui.transfer.items') }}</th>
                                <th class="py-2 font-medium">{{ __('ui.transfer.status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($transfers as $transfer)
                                <tr class="border-b border-gray-50 last:border-0">
                                    <td class="py-2 pe-4 font-medium"><a href="{{ route('transfers.show', $transfer) }}" class="text-lentera-800 underline-offset-2 hover:underline">{{ $transfer->number }}</a></td>
                                    <td class="py-2 pe-4">{{ $transfer->toBranch->name }}</td>
                                    <td class="py-2 pe-4">{{ $transfer->items->map(fn ($i) => $i->product->name.' ×'.$i->qty)->join(', ') }}</td>
                                    <td class="py-2">{{ __('ui.transfer.statuses.'.$transfer->status) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            <h2 class="mb-2 text-base font-semibold text-gray-800">{{ __('ui.purchasing.receipts') }}</h2>
            <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.purchasing.receipt_number') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.purchasing.received_date') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.purchasing.delivery_note') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.purchasing.total') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.purchasing.remaining') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.purchasing.pay_status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($po->receipts as $receipt)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 font-medium"><a href="{{ route('receipts.show', $receipt) }}" class="text-lentera-800 underline-offset-2 hover:underline">{{ $receipt->number }}</a></td>
                                <td class="px-4 py-3">{{ $receipt->received_date->format('d/m/Y') }}</td>
                                <td class="px-4 py-3">{{ $receipt->delivery_note_no ?: '—' }}</td>
                                <td class="px-4 py-3 tnum">{{ $receipt->totalAmount()->format() }}</td>
                                <td class="px-4 py-3 tnum">{{ $receipt->remainingAmount()->format() }}</td>
                                <td class="px-4 py-3">{{ __('ui.purchasing.pay_statuses.'.$receipt->paymentStatus()) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><x-lentera.empty-state>{{ __('ui.purchasing.empty_receipts') }}</x-lentera.empty-state></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
