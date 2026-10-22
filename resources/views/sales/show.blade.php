<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ $sale->number }}</h1>
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
                <a href="{{ route('sales.index') }}" class="text-sm text-gray-600 underline">{{ __('ui.back') }}</a>
                <div class="flex items-center gap-2">
                    <x-lentera.aksi-bar>
                        <x-lentera.menu-item :href="route('sales.print-order', $sale)" target="_blank" print>{{ __('ui.sales.print_order') }}</x-lentera.menu-item>
                        <x-lentera.menu-item :href="route('sales.print-invoice', $sale)" target="_blank" print>{{ __('ui.sales.print_invoice') }}</x-lentera.menu-item>
                        @if ($sale->prescription_snapshot)
                            <x-lentera.menu-item :href="route('sales.print-prescription', $sale)" target="_blank" print>{{ __('ui.sales.print_prescription') }}</x-lentera.menu-item>
                        @endif
                    </x-lentera.aksi-bar>
                    @unless ($sale->isVoid())
                        <x-lentera.aksi-bar>
                            @can('sales.create')
                                <x-lentera.menu-item :href="route('sales.create', ['pelanggan' => $sale->customer_id])">{{ __('ui.sales.create') }}</x-lentera.menu-item>
                            @endcan
                            @can('sales.pay')
                                @if ($sale->paymentStatus() !== 'paid')
                                    <x-lentera.menu-item :href="route('sales.payment', $sale)">{{ __('ui.sales.add_payment') }}</x-lentera.menu-item>
                                    @if ($sale->installments->isEmpty())
                                        <x-lentera.menu-item :href="route('sales.installment', $sale)">{{ __('ui.sales.create_schedule') }}</x-lentera.menu-item>
                                    @endif
                                @endif
                            @endcan
                            @can('sales.return')
                                <x-lentera.menu-item :href="route('sales.return', $sale)">{{ __('ui.sales.make_return') }}</x-lentera.menu-item>
                            @endcan
                            @can('sales.void')

                                <x-lentera.menu-item :href="route('sales.void', $sale)" danger>{{ __('ui.sales.void') }}</x-lentera.menu-item>
                            @endcan
                        </x-lentera.aksi-bar>
                    @endunless
                </div>
            </div>

            @if ($sale->isVoid())
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ __('ui.sales.status_void') }} — {{ $sale->void_reason }} ({{ $sale->voided_at?->format('d/m/Y H:i') }})
                </div>
            @endif

            <div class="mb-6 rounded-lg bg-white p-6 shadow-soft">
                <dl class="grid grid-cols-[160px_1fr] gap-y-3 text-sm">
                    <dt class="text-gray-500">{{ __('ui.sales.customer') }}</dt>
                    <dd class="font-medium text-gray-800"><a class="text-lentera-800 underline-offset-2 hover:underline" href="{{ route('customers.show', $sale->customer) }}">{{ $sale->customer->name }}</a> · {{ $sale->customer->phone }}</dd>
                    <dt class="text-gray-500">{{ __('ui.sales.date') }}</dt>
                    <dd class="font-medium text-gray-800">{{ $sale->sale_date->format('d/m/Y') }}</dd>
                    <dt class="text-gray-500">{{ __('ui.sales.branch') }}</dt>
                    <dd class="font-medium text-gray-800">{{ $sale->branch->name }}</dd>
                    <dt class="text-gray-500">{{ __('ui.sales.cashier') }}</dt>
                    <dd class="font-medium text-gray-800">{{ $sale->cashier->name }}</dd>
                    <dt class="text-gray-500">{{ __('ui.sales.pay_status') }}</dt>
                    <dd class="font-medium text-gray-800">{{ __('ui.sales.pay_'.$sale->paymentStatus()) }}</dd>
                    @if ($procurementStage !== null)
                        <dt class="text-gray-500">{{ __('ui.lab.lens_status') }}</dt>
                        <dd class="font-medium text-gray-800">
                            {{ __('ui.lab.stages.'.$procurementStage) }}
                            @foreach ($linkedPrs as $linkedPr)
                                · <a class="text-lentera-800 underline-offset-2 hover:underline" href="{{ route('pr.show', $linkedPr) }}">{{ $linkedPr->number }}</a>
                            @endforeach
                        </dd>
                    @endif
                    @if ($sale->doctor)
                        <dt class="text-gray-500">{{ __('ui.sales.doctor') }}</dt>
                        <dd class="font-medium text-gray-800">{{ $sale->doctor->name }}</dd>
                    @endif
                    @if ($sale->notes)
                        <dt class="text-gray-500">{{ __('ui.sales.notes') }}</dt>
                        <dd class="font-medium text-gray-800">{{ $sale->notes }}</dd>
                    @endif
                </dl>
            </div>

            @if ($sale->prescription_snapshot)
                <h2 class="mb-2 text-base font-semibold text-gray-800">{{ __('ui.sales.prescription') }}</h2>
                <div class="mb-6 rounded-lg bg-white p-6 shadow-soft">
                    <div class="grid gap-4 md:grid-cols-2">
                        @foreach (['od' => 'eye_right', 'os' => 'eye_left'] as $side => $labelKey)
                            <div class="rounded-lg border border-gray-200 p-4">
                                <h3 class="mb-3 text-sm font-semibold text-lentera-900">{{ __('ui.prescriptions.'.$labelKey) }}</h3>
                                <dl class="grid grid-cols-2 gap-y-2 text-sm tnum">
                                    <dt class="text-gray-500">{{ __('ui.prescriptions.sph') }}</dt>
                                    <dd class="font-medium text-gray-800">{{ $sale->prescription_snapshot[$side]['sph'] }}</dd>
                                    <dt class="text-gray-500">{{ __('ui.prescriptions.cyl') }}</dt>
                                    <dd class="font-medium text-gray-800">{{ $sale->prescription_snapshot[$side]['cyl'] }}</dd>
                                    <dt class="text-gray-500">{{ __('ui.prescriptions.axis') }}</dt>
                                    <dd class="font-medium text-gray-800">{{ $sale->prescription_snapshot[$side]['axis'] ?? '—' }}</dd>
                                    <dt class="text-gray-500">{{ __('ui.prescriptions.add') }}</dt>
                                    <dd class="font-medium text-gray-800">{{ $sale->prescription_snapshot[$side]['add'] ?? '—' }}</dd>
                                </dl>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <h2 class="mb-2 text-base font-semibold text-gray-800">{{ __('ui.sales.items') }}</h2>
            <div class="mb-6 overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.sales.item') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.sales.qty') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.sales.unit_price') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.sales.discount') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.sales.line_total') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sale->items as $item)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 font-medium text-gray-800">{{ $item->product_name_snapshot }} <span class="text-xs text-gray-400">({{ __('ui.products.types.'.$item->type) }}{{ $item->prescription_ref ? ' · '.strtoupper($item->prescription_ref) : '' }})</span></td>
                                <td class="px-4 py-3 tnum">{{ $item->qty }}</td>
                                <td class="px-4 py-3 tnum">{{ \App\Support\Money::fromDecimal((string) $item->unit_price)->format() }}</td>
                                <td class="px-4 py-3 tnum">{{ \App\Support\Money::fromDecimal((string) $item->discount_amount)->format() }}</td>
                                <td class="px-4 py-3 tnum">{{ \App\Support\Money::fromDecimal((string) $item->line_total)->format() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="text-sm">
                            <td colspan="4" class="px-4 py-2 text-right text-gray-500">{{ __('ui.sales.subtotal') }}</td>
                            <td class="px-4 py-2 tnum font-medium">{{ \App\Support\Money::fromDecimal((string) $sale->subtotal_amount)->format() }}</td>
                        </tr>
                        <tr class="text-sm">
                            <td colspan="4" class="px-4 py-2 text-right text-gray-500">{{ __('ui.sales.discount') }}</td>
                            <td class="px-4 py-2 tnum font-medium">−{{ \App\Support\Money::fromDecimal((string) $sale->discount_amount)->format() }}</td>
                        </tr>
                        <tr class="text-sm">
                            <td colspan="4" class="px-4 py-2 text-right text-gray-500">{{ __('ui.sales.tax') }} ({{ $sale->tax_mode }})</td>
                            <td class="px-4 py-2 tnum font-medium">{{ \App\Support\Money::fromDecimal((string) $sale->tax_amount)->format() }}</td>
                        </tr>
                        <tr class="text-sm font-semibold">
                            <td colspan="4" class="px-4 py-2 text-right">{{ __('ui.sales.total') }}</td>
                            <td class="px-4 py-2 tnum">{{ \App\Support\Money::fromDecimal((string) $sale->total_amount)->format() }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            @foreach ($sale->items as $item)
                @if ($item->type === 'rx' && $item->rx_parameters)
                    <div class="mb-6 rounded-lg bg-white p-5 shadow-soft">
                        <div class="mb-3 flex flex-wrap items-center gap-3">
                            <h2 class="text-base font-semibold text-gray-800">{{ __('ui.sales.precal') }} — {{ $item->product_name_snapshot }}</h2>
                            @if ($item->rx_parameters['precal_complete'] ?? false)
                                <span class="rounded-full bg-lentera-100 px-2.5 py-0.5 text-xs font-semibold text-lentera-900">{{ __('ui.sales.precal_complete') }}</span>
                            @else
                                <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800">{{ __('ui.sales.precal_incomplete') }}</span>
                            @endif
                            @if (! $sale->isVoid())
                                @can('sales.update')
                                    <span class="ml-auto">
                                        <x-lentera.aksi-dialog :label="__('ui.sales.precal_edit')" :title="__('ui.sales.precal_edit').' — '.$item->product_name_snapshot" :action="route('sales.precal.update', [$sale, $item])" :confirmText="__('ui.save')" sm>
                                            <div class="grid grid-cols-3 gap-3">
                                                @foreach (\App\Modules\Sales\Services\RxPrecalValidator::KEYS as $key)
                                                    <div @if ($key === 'type_frame') class="col-span-2" @endif>
                                                        <label class="text-xs font-medium uppercase text-gray-500">{{ $key === 'type_frame' ? 'Type Frame' : strtoupper($key) }}</label>
                                                        @if ($key === 'de')
                                                            <select name="precal[de]" class="mt-0.5 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                                                                <option value="">—</option>
                                                                <option value="R" @selected(($item->rx_parameters['precal']['de'] ?? null) === 'R')>R</option>
                                                                <option value="L" @selected(($item->rx_parameters['precal']['de'] ?? null) === 'L')>L</option>
                                                            </select>
                                                        @else
                                                            <input type="text" name="precal[{{ $key }}]" value="{{ $item->rx_parameters['precal'][$key] ?? '' }}" @if ($key === 'type_frame') maxlength="100" @else inputmode="decimal" @endif class="tnum mt-0.5 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        </x-lentera.aksi-dialog>
                                    </span>
                                @endcan
                            @endif
                        </div>
                        <div class="grid grid-cols-3 gap-3 sm:grid-cols-4 lg:grid-cols-6">
                            @foreach (\App\Modules\Sales\Services\RxPrecalValidator::KEYS as $key)
                                <div>
                                    <div class="text-xs font-medium uppercase text-gray-400">{{ $key === 'type_frame' ? 'Type Frame' : strtoupper($key) }}</div>
                                    <div class="tnum text-sm font-medium text-gray-800">{{ $item->rx_parameters['precal'][$key] ?? '—' }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach

            <h2 class="mb-2 text-base font-semibold text-gray-800">{{ __('ui.sales.payments') }}</h2>
            <div class="mb-6 overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.sales.date') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.sales.method') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.sales.amount') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.sales.reference') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.sales.received_by') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($sale->payments as $payment)
                            <tr class="border-b border-gray-50 last:border-0 {{ $payment->amount < 0 ? 'text-red-600' : '' }}">
                                <td class="px-4 py-3">{{ $payment->paid_at->format('d/m/Y H:i') }}</td>
                                <td class="px-4 py-3">{{ __('ui.sales.methods.'.$payment->method) }}{{ $payment->isReversal() ? ' ↩' : '' }}</td>
                                <td class="px-4 py-3 tnum">{{ \App\Support\Money::fromDecimal((string) $payment->amount)->format() }}</td>
                                <td class="px-4 py-3">{{ $payment->reference ?: '—' }}</td>
                                <td class="px-4 py-3">{{ $payment->receivedBy->name }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><x-lentera.empty-state>{{ __('ui.sales.no_payments') }}</x-lentera.empty-state></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($sale->installments->isNotEmpty())
                <h2 class="mb-2 text-base font-semibold text-gray-800">{{ __('ui.sales.installment_schedule') }}</h2>
                <div class="mb-6 overflow-hidden rounded-lg bg-white shadow-soft">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 text-left text-gray-500">
                                <th class="px-4 py-3 font-medium">{{ __('ui.sales.sequence') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('ui.sales.due_date') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('ui.sales.amount') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('ui.sales.paid') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('ui.sales.remaining') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('ui.sales.status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($sale->installments as $installment)
                                <tr class="border-b border-gray-50 last:border-0">
                                    <td class="px-4 py-3">{{ $installment->sequence }}</td>
                                    <td class="px-4 py-3">{{ $installment->due_date->format('d/m/Y') }}</td>
                                    <td class="px-4 py-3 tnum">{{ \App\Support\Money::fromDecimal((string) $installment->amount)->format() }}</td>
                                    <td class="px-4 py-3 tnum">{{ \App\Support\Money::fromDecimal((string) $installment->paid_amount)->format() }}</td>
                                    <td class="px-4 py-3 tnum">{{ $installment->remaining()->format() }}</td>
                                    <td class="px-4 py-3">
                                        @if ($installment->effectiveStatus() === 'overdue')
                                            <span class="rounded bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700">{{ __('ui.sales.inst_overdue') }}</span>
                                        @elseif ($installment->status === 'paid')
                                            <span class="rounded bg-lentera-50 px-2 py-0.5 text-xs font-medium text-lentera-800">{{ __('ui.sales.inst_paid') }}</span>
                                        @elseif ($installment->status === 'partial')
                                            <span class="rounded bg-gold/10 px-2 py-0.5 text-xs font-medium text-gold-dark">{{ __('ui.sales.inst_partial') }}</span>
                                        @else
                                            <span class="rounded bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">{{ __('ui.sales.inst_open') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($sale->returns->isNotEmpty())
                <h2 class="mb-2 text-base font-semibold text-gray-800">{{ __('ui.sales.returns') }}</h2>
                <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 text-left text-gray-500">
                                <th class="px-4 py-3 font-medium">{{ __('ui.sales.number') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('ui.sales.date') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('ui.sales.return_reason') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('ui.sales.refund_method') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('ui.sales.total') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($sale->returns as $return)
                                <tr class="border-b border-gray-50 last:border-0">
                                    <td class="px-4 py-3 font-medium text-gray-800">{{ $return->number }}</td>
                                    <td class="px-4 py-3">{{ $return->created_at->format('d/m/Y') }}</td>
                                    <td class="px-4 py-3">{{ $return->reason }}</td>
                                    <td class="px-4 py-3">{{ __('ui.sales.refund_methods.'.$return->refund_method) }}</td>
                                    <td class="px-4 py-3 tnum">{{ \App\Support\Money::fromDecimal((string) $return->total_amount)->format() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
