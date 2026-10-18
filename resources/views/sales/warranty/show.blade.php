<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.warranty.detail') }} {{ $case->number }}</h1>
    </x-slot>

    @php $r = $case->replacement; @endphp
    <div class="py-8">
        <div class="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-lg border border-lentera-200 bg-lentera-50 px-4 py-3 text-sm text-lentera-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
            @endif

            <div class="flex items-start justify-between">
                <div class="rounded-lg bg-white p-6 shadow-soft flex-1 mr-4">
                    <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm sm:grid-cols-3">
                        <div><dt class="text-gray-400">{{ __('ui.warranty.invoice') }}</dt><dd class="font-medium">{{ $case->sale->number }}</dd></div>
                        <div><dt class="text-gray-400">{{ __('ui.warranty.customer') }}</dt><dd class="font-medium">{{ $case->sale->customer->name ?? '—' }}</dd></div>
                        <div><dt class="text-gray-400">{{ __('ui.warranty.status') }}</dt><dd><span class="inline-flex items-center rounded-full bg-lentera-50 px-2.5 py-0.5 text-xs font-medium text-lentera-800 ring-1 ring-inset ring-lentera-600/20">{{ __('ui.warranty.status_'.$case->status) }}</span></dd></div>
                        <div><dt class="text-gray-400">{{ __('ui.warranty.item') }}</dt><dd class="font-medium">{{ $case->saleItem->product_name_snapshot }} × {{ $r?->quantity }}</dd></div>
                        <div><dt class="text-gray-400">{{ __('ui.warranty.branch') }}</dt><dd class="font-medium">{{ $case->branch->name }}</dd></div>
                        <div><dt class="text-gray-400">{{ __('ui.warranty.warehouse') }}</dt><dd class="font-medium">{{ $warehouse->name }}</dd></div>
                        <div class="col-span-2"><dt class="text-gray-400">{{ __('ui.warranty.reason') }}</dt><dd class="font-medium">{{ $case->reason }}</dd></div>
                        @if ($case->description)
                            <div class="col-span-2"><dt class="text-gray-400">{{ __('ui.warranty.description') }}</dt><dd>{{ $case->description }}</dd></div>
                        @endif
                    </dl>
                    <p class="mt-4 text-xs text-gray-400">{{ __('ui.warranty.flow_hint') }}</p>
                </div>
                <x-lentera.dropdown-aksi :label="__('ui.common.actions')">
                    @if ($case->status === 'open')
                        <form method="POST" action="{{ route('warranties.send-old', $case) }}">@csrf<button type="submit" class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-lentera-50">{{ __('ui.warranty.act_send_old') }}</button></form>
                    @endif
                    @if ($case->status === 'waiting_warehouse')
                        <form method="POST" action="{{ route('warranties.receive-old', $case) }}">@csrf<button type="submit" class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-lentera-50">{{ __('ui.warranty.act_receive_old') }}</button></form>
                    @endif
                    @if ($case->status === 'replacement_preparing' && $ready)
                        <form method="POST" action="{{ route('warranties.ship', $case) }}">@csrf<button type="submit" class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-lentera-50">{{ __('ui.warranty.act_ship') }}</button></form>
                    @endif
                    @if ($case->status === 'replacement_shipped')
                        <form method="POST" action="{{ route('warranties.receive-replacement', $case) }}">@csrf<button type="submit" class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-lentera-50">{{ __('ui.warranty.act_receive_replacement') }}</button></form>
                    @endif
                    @if ($case->status === 'replacement_received')
                        <form method="POST" action="{{ route('warranties.complete', $case) }}">@csrf<button type="submit" class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-lentera-50">{{ __('ui.warranty.act_complete') }}</button></form>
                    @endif
                </x-lentera.dropdown-aksi>
            </div>

            @foreach ($case->brokenStocks as $broken)
                <div class="rounded-lg bg-white p-6 shadow-soft">
                    <h2 class="font-semibold text-gray-800">{{ __('ui.warranty.broken_title') }} — {{ $broken->product->name }} × {{ $broken->quantity }} ({{ $broken->status }})</h2>
                    @if ($broken->status === 'pending_inspection')
                        <form method="POST" action="{{ route('warranties.inspect', $broken) }}" class="mt-4 flex flex-wrap items-end gap-3">
                            @csrf
                            <div>
                                <x-input-label for="condition_{{ $broken->id }}" :value="__('ui.warranty.condition')" />
                                <select id="condition_{{ $broken->id }}" name="condition" class="mt-1 rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                                    <option value="good">{{ __('ui.warranty.cond_good') }}</option>
                                    <option value="broken">{{ __('ui.warranty.cond_broken') }}</option>
                                </select>
                            </div>
                            <div class="flex-1 min-w-48">
                                <x-input-label for="ireason_{{ $broken->id }}" :value="__('ui.warranty.inspect_reason')" />
                                <x-text-input id="ireason_{{ $broken->id }}" name="reason" class="mt-1 block w-full" required maxlength="500" />
                            </div>
                            <div class="flex-1 min-w-48">
                                <x-input-label for="inote_{{ $broken->id }}" :value="__('ui.warranty.note')" />
                                <x-text-input id="inote_{{ $broken->id }}" name="note" class="mt-1 block w-full" maxlength="1000" />
                            </div>
                            <x-primary-button>{{ __('ui.warranty.inspect') }}</x-primary-button>
                        </form>
                    @elseif ($broken->status === 'at_warehouse')
                        <form method="POST" action="{{ route('warranties.adjust', $broken) }}" class="mt-4 flex flex-wrap items-end gap-3">
                            @csrf
                            <div class="flex-1 min-w-48">
                                <x-input-label for="areason_{{ $broken->id }}" :value="__('ui.warranty.reason')" />
                                <x-text-input id="areason_{{ $broken->id }}" name="reason" class="mt-1 block w-full" required maxlength="500" />
                            </div>
                            <div class="flex-1 min-w-48">
                                <x-input-label for="anote_{{ $broken->id }}" :value="__('ui.warranty.note')" />
                                <x-text-input id="anote_{{ $broken->id }}" name="note" class="mt-1 block w-full" required maxlength="1000" />
                            </div>
                            <x-danger-button>{{ __('ui.warranty.adjust') }}</x-danger-button>
                        </form>
                    @else
                        <p class="mt-2 text-sm text-gray-500">{{ $broken->resolution }} — {{ $broken->reason }}</p>
                    @endif
                </div>
            @endforeach

            @if ($case->status === 'old_item_received')
                <div class="rounded-lg bg-white p-6 shadow-soft">
                    <h2 class="font-semibold text-gray-800">{{ __('ui.warranty.act_prepare') }}</h2>
                    <form method="POST" action="{{ route('warranties.prepare', $case) }}" class="mt-4 grid gap-4 sm:grid-cols-4">
                        @csrf
                        <div>
                            <x-input-label for="source" :value="__('ui.warranty.source')" />
                            <select id="source" name="source" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                                <option value="stock">{{ __('ui.warranty.source_stock') }}</option>
                                <option value="purchase_order">{{ __('ui.warranty.source_po') }}</option>
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <x-input-label for="product_id" :value="__('ui.warranty.product')" />
                            <select id="product_id" name="product_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                                @foreach ($products as $product)
                                    <option value="{{ $product->id }}" @selected($product->id === $case->saleItem->product_id)>{{ $product->name }} ({{ $product->sku }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="customer_charge" :value="__('ui.warranty.customer_charge')" />
                            <x-text-input id="customer_charge" name="customer_charge" type="number" min="0" step="0.01" value="0" class="mt-1 block w-full" required />
                        </div>
                        <div class="sm:col-span-4 flex justify-end">
                            <x-primary-button>{{ __('ui.warranty.act_prepare') }}</x-primary-button>
                        </div>
                    </form>
                </div>
            @endif

            @if ($r)
                <div class="rounded-lg bg-white p-6 shadow-soft">
                    <h2 class="font-semibold text-gray-800">{{ __('ui.warranty.replacement') }}</h2>
                    <dl class="mt-3 grid grid-cols-2 gap-x-6 gap-y-3 text-sm sm:grid-cols-3">
                        <div><dt class="text-gray-400">{{ __('ui.warranty.source') }}</dt><dd class="font-medium">{{ __('ui.warranty.source_'.($r->source === 'purchase_order' ? 'po' : $r->source)) }}</dd></div>
                        <div><dt class="text-gray-400">{{ __('ui.warranty.product') }}</dt><dd class="font-medium">{{ $r->product->name ?? '—' }}</dd></div>
                        <div><dt class="text-gray-400">{{ __('ui.warranty.status') }}</dt><dd class="font-medium">{{ $r->status }}{{ $case->status === 'replacement_preparing' ? ($ready ? ' — '.__('ui.warranty.ready') : ' — '.__('ui.warranty.not_ready')) : '' }}</dd></div>
                        <div><dt class="text-gray-400">{{ __('ui.warranty.original_value') }}</dt><dd class="font-medium">{{ \App\Support\Money::fromDecimal((string) $r->original_value)->format() }}</dd></div>
                        <div><dt class="text-gray-400">{{ __('ui.warranty.replacement_value') }}</dt><dd class="font-medium">{{ \App\Support\Money::fromDecimal((string) $r->replacement_value)->format() }}</dd></div>
                        <div><dt class="text-gray-400">{{ __('ui.warranty.customer_charge') }}</dt><dd class="font-medium">{{ \App\Support\Money::fromDecimal((string) $r->customer_charge)->format() }}</dd></div>
                    </dl>
                    @if ($r->source === 'purchase_order' && $r->purchase_order_id === null && $case->status === 'replacement_preparing')
                        <form method="POST" action="{{ route('warranties.replacement-po', $case) }}" class="mt-4 flex flex-wrap items-end gap-3">
                            @csrf
                            <div>
                                <x-input-label for="supplier_id" :value="__('ui.warranty.supplier')" />
                                <select id="supplier_id" name="supplier_id" class="mt-1 rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                                    @foreach ($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <x-primary-button>{{ __('ui.warranty.act_po') }}</x-primary-button>
                        </form>
                    @endif
                    @if ($r->purchaseOrder)
                        <p class="mt-3 text-sm text-gray-500">{{ __('ui.warranty.po') }}: <a href="{{ route('purchase-orders.show', $r->purchaseOrder) }}" class="text-lentera-700 hover:underline">{{ $r->purchaseOrder->number }}</a> ({{ $r->purchaseOrder->status }})</p>
                    @endif
                </div>

                <div class="rounded-lg bg-white p-6 shadow-soft">
                    <h2 class="font-semibold text-gray-800">{{ __('ui.warranty.payments') }}</h2>
                    <p class="mt-2 text-sm text-gray-500">{{ __('ui.warranty.charge_paid') }}: {{ \App\Support\Money::fromDecimal($case->paidAmount())->format() }} · {{ __('ui.warranty.charge_remaining') }}: {{ \App\Support\Money::fromDecimal((string) $r->customer_charge)->sub(\App\Support\Money::fromDecimal($case->paidAmount()))->format() }}</p>
                    @can('sales.pay')
                        @if ($case->status !== 'completed')
                            <form method="POST" action="{{ route('warranties.pay', $case) }}" class="mt-4 flex flex-wrap items-end gap-3">
                                @csrf
                                <div>
                                    <x-input-label for="method" :value="__('ui.warranty.method')" />
                                    <select id="method" name="method" class="mt-1 rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                                        <option value="cash">Cash</option>
                                        <option value="qris">QRIS</option>
                                        <option value="transfer">Transfer</option>
                                        <option value="card">Card</option>
                                    </select>
                                </div>
                                <div>
                                    <x-input-label for="amount" :value="__('ui.warranty.amount')" />
                                    <x-text-input id="amount" name="amount" type="number" min="1" step="0.01" class="mt-1 block" required />
                                </div>
                                <x-primary-button>{{ __('ui.warranty.pay') }}</x-primary-button>
                            </form>
                        @endif
                    @endcan
                    @if ($case->payments->isNotEmpty())
                        <ul class="mt-3 space-y-1 text-sm text-gray-600">
                            @foreach ($case->payments as $payment)
                                <li>{{ $payment->payment_date->format('d/m/Y') }} — {{ $payment->method }} — {{ \App\Support\Money::fromDecimal((string) $payment->amount)->format() }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
