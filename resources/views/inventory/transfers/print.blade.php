<x-print-layout :title="__('ui.transfer.print')" :store-name="$storeName" :paper="'A5 portrait'">
    <h1 style="font-size:14pt; margin:0 0 2mm;">{{ __('ui.transfer.print') }} — {{ $transfer->number }}</h1>
    <p style="margin:0 0 4mm;">{{ __('ui.transfer.from') }}: {{ $transfer->fromBranch->name }} · {{ __('ui.transfer.to') }}: {{ $transfer->toBranch->name }} · {{ $transfer->created_at->format('d/m/Y') }} · {{ __('ui.transfer.sender') }}: {{ $transfer->sender->name }}</p>

    <table style="width:100%; border-collapse:collapse;" border="1" cellpadding="4">
        <thead>
            <tr>
                <th>{{ __('ui.purchasing.product') }}</th>
                <th>SKU</th>
                <th>{{ __('ui.purchasing.qty') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($transfer->items as $item)
                <tr>
                    <td>{{ $item->product->name }}</td>
                    <td>{{ $item->product->sku }}</td>
                    <td>{{ $item->qty }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</x-print-layout>
