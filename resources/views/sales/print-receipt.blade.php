@php
    $storeName = \App\Modules\Master\Models\Setting::value('store.name', 'Lentera');
    $storeAddress = \App\Modules\Master\Models\Setting::value('store.address');
    $storePhone = \App\Modules\Master\Models\Setting::value('store.phone');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('ui.sales.receipt_doc') }} — {{ $sale->number }}</title>
    <style>
        @page { size: {{ $width }}mm auto; margin: 3mm; }
        * { box-sizing: border-box; }
        body { margin: 0 auto; width: {{ $width - 6 }}mm; font-family: ui-monospace, "Courier New", monospace; font-size: {{ $width === 58 ? '10px' : '11px' }}; color: #111; }
        .c { text-align: center; } .r { text-align: right; }
        .store { font-size: 1.35em; font-weight: 700; }
        hr { border: 0; border-top: 1px dashed #111; margin: 2mm 0; }
        table { width: 100%; border-collapse: collapse; }
        td { vertical-align: top; padding: 0.4mm 0; }
        .row { display: flex; justify-content: space-between; gap: 2mm; }
        .total { font-size: 1.2em; font-weight: 700; }
        @media print { .noprint { display: none; } }
    </style>
</head>
<body>
    <div class="c">
        <div class="store">{{ $storeName }}</div>
        @if ($storeAddress)<div>{{ $storeAddress }}</div>@endif
        @if ($storePhone)<div>{{ $storePhone }}</div>@endif
    </div>
    <hr>
    <div class="row"><span>{{ $sale->number }}</span><span>{{ $sale->sale_date->format('d/m/Y') }} {{ $sale->created_at->format('H.i') }}</span></div>
    <div class="row"><span>{{ __('ui.sales.cashier') }}: {{ $sale->cashier?->name }}</span><span>{{ $sale->branch->name }}</span></div>
    <div>{{ __('ui.sales.customer') }}: {{ $sale->customer->name }}</div>
    <hr>
    @foreach ($sale->items as $item)
        <div>{{ $item->product_name_snapshot }}</div>
        <div class="row"><span>{{ $item->qty }} × {{ \App\Support\Money::fromDecimal((string) $item->unit_price)->format() }}</span><span>{{ \App\Support\Money::fromDecimal((string) $item->line_total)->format() }}</span></div>
    @endforeach
    <hr>
    <table>
        <tr><td>{{ __('ui.sales.subtotal') }}</td><td class="r">{{ \App\Support\Money::fromDecimal((string) $sale->subtotal_amount)->format() }}</td></tr>
        @if ((float) $sale->discount_amount > 0)
            <tr><td>{{ __('ui.sales.discount') }}</td><td class="r">−{{ \App\Support\Money::fromDecimal((string) $sale->discount_amount)->format() }}</td></tr>
        @endif
        @if ((float) $sale->tax_amount > 0)
            <tr><td>{{ __('ui.sales.tax') }}</td><td class="r">{{ \App\Support\Money::fromDecimal((string) $sale->tax_amount)->format() }}</td></tr>
        @endif
        <tr><td class="total">{{ __('ui.sales.total') }}</td><td class="r total">{{ \App\Support\Money::fromDecimal((string) $sale->total_amount)->format() }}</td></tr>
    </table>
    <hr>
    @foreach ($sale->payments as $payment)
        <div class="row"><span>{{ __('ui.sales.methods.'.$payment->method) }}</span><span>{{ \App\Support\Money::fromDecimal((string) $payment->amount)->format() }}</span></div>
    @endforeach
    <div class="row"><span>{{ __('ui.sales.paid') }}</span><span>{{ \App\Support\Money::fromDecimal((string) $sale->paid_amount)->format() }}</span></div>
    <div class="row"><span>{{ __('ui.sales.remaining') }}</span><span>{{ $sale->remaining()->format() }}</span></div>
    <div class="row"><span>{{ __('ui.sales.pay_status') }}</span><span>{{ __('ui.sales.pay_'.$sale->paymentStatus()) }}</span></div>
    <hr>
    <div class="c">{{ __('ui.print_doc.thanks') }}</div>
    <div class="c" style="margin-top:2mm;">{{ __('ui.print_doc.printed_at') }} {{ now()->format('d/m/Y H.i') }} {{ __('ui.print_doc.by') }} {{ $printedBy?->name }}</div>
</body>
</html>
