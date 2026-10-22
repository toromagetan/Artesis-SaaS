<x-print-layout :title="__('ui.print_doc.payment_receipt_doc')" :store-name="$storeName" :printed-by="$printedBy" paper="A5 portrait">
    <h1 style="font-size:14pt; margin:0 0 2mm;">{{ __('ui.print_doc.payment_receipt_doc') }} — {{ $payment->number }}</h1>
    <p style="margin:0 0 4mm;">
        {{ __('ui.purchasing.supplier') }}: {{ $payment->supplier->name }} ·
        {{ __('ui.purchasing.payment_date') }}: {{ $payment->payment_date->format('d/m/Y') }} ·
        {{ __('ui.purchasing.method') }}: {{ $payment->method }}
        @if ($payment->reference) · {{ __('ui.purchasing.reference') }}: {{ $payment->reference }} @endif
    </p>

    <p style="font-size:16pt; margin:0 0 4mm;"><strong>{{ \App\Support\Money::fromDecimal((string) $payment->amount)->format() }}</strong></p>

    <table style="width:100%; border-collapse:collapse;" border="1" cellpadding="4">
        <thead>
            <tr>
                <th>{{ __('ui.purchasing.receipt_number') }}</th>
                <th>{{ __('ui.purchasing.number') }} PO</th>
                <th>{{ __('ui.purchasing.amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($payment->allocations as $allocation)
                <tr>
                    <td>{{ $allocation->receipt->number }}</td>
                    <td>{{ $allocation->receipt->purchaseOrder->number }}</td>
                    <td>{{ \App\Support\Money::fromDecimal((string) $allocation->amount)->format() }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table style="width:100%; margin-top:10mm;">
        <tr>
            <td style="width:50%;">{{ __('ui.print_doc.paid_by') }}<br><br><br>{{ $payment->payer?->name }}</td>
            <td>{{ __('ui.print_doc.received_by') }}<br><br><br>{{ $payment->supplier->name }}</td>
        </tr>
    </table>
</x-print-layout>
