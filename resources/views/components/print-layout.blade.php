<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $title ?? config('app.name', 'Artesis') }}</title>
    <style>
        /* Mesin cetak dasar Lentera (Blueprint E.5): dokumen PENUH di tab baru,
           kertas bawaan A5 potret; dokumen lain dapat menimpa lewat $paper. */
        @page { size: {{ $paper ?? 'A5 portrait' }}; margin: 12mm; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Plus Jakarta Sans", "Segoe UI", Arial, sans-serif; font-size: 11px; color: #1f2937; }
        .store { text-align: center; border-bottom: 2px solid #c9a227; padding-bottom: 8px; margin-bottom: 12px; }
        .store .name { font-size: 18px; font-weight: 800; color: #022c22; letter-spacing: -0.01em; }
        .doc-title { font-size: 14px; font-weight: 700; color: #064e3b; margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th, td { border-bottom: 1px solid #e5e7eb; padding: 4px 6px; text-align: left; vertical-align: top; }
        th { font-size: 10px; text-transform: uppercase; letter-spacing: 0.04em; color: #6b7280; }
        .tnum { font-variant-numeric: tabular-nums; }
        dl { display: grid; grid-template-columns: 110px 1fr; row-gap: 4px; margin-bottom: 10px; }
        dt { color: #6b7280; } dd { font-weight: 600; }
        .footer { margin-top: 14px; border-top: 1px solid #e5e7eb; padding-top: 6px; font-size: 9px; color: #9ca3af; display: flex; justify-content: space-between; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    @php
        // Identitas toko dari Pengaturan Umum (B2) bila pemanggil tidak
        // memberinya secara eksplisit — berlaku ke semua dokumen cetak.
        $storeLogo = $storeLogo ?? \App\Modules\Master\Models\Setting::value('store.logo_path');
        $storeName = $storeName ?? \App\Modules\Master\Models\Setting::value('store.name', config('app.name', 'Artesis'));
        $storeAddress = $storeAddress ?? \App\Modules\Master\Models\Setting::value('store.address');
        $storePhone = $storePhone ?? \App\Modules\Master\Models\Setting::value('store.phone');
    @endphp
    <div class="store">
        @if ($storeLogo)
            <img src="{{ asset($storeLogo) }}" alt="" style="max-height: 14mm; max-width: 60mm; margin-bottom: 4px;">
        @endif
        <div class="name">{{ $storeName }}</div>
        @if ($storeAddress)
            <div>{{ $storeAddress }}</div>
        @endif
        @if ($storePhone)
            <div>{{ $storePhone }}</div>
        @endif
    </div>

    {{ $slot }}

    <div class="footer">
        <span>{{ __('ui.print_doc.printed_at') }} {{ now()->format('d/m/Y H:i') }} {{ __('ui.print_doc.by') }} {{ $printedBy?->name ?? '—' }}</span>
        <span>Artesis</span>
    </div>
</body>
</html>
