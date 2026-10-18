<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('ui.portal.title') }} — Lentera</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#f7f8f7] font-sans text-gray-800 antialiased">
    <div class="mx-auto max-w-3xl px-4 py-8">
        <header class="mb-6 flex items-center gap-3">
            <span class="flex h-10 w-10 items-center justify-center rounded-full bg-lentera-900 text-lg font-bold text-gold">L</span>
            <div>
                <p class="text-lg font-bold leading-tight text-lentera-950">Lentera</p>
                <p class="text-xs text-gray-500">{{ __('ui.portal.tagline') }}</p>
            </div>
        </header>

        <section class="mb-6 rounded-2xl bg-lentera-950 p-6 text-white shadow-soft">
            <p class="text-sm text-lentera-200">{{ __('ui.portal.greeting') }}</p>
            <h1 class="mb-4 text-2xl font-bold">{{ $customer->name }}</h1>
            <div class="flex flex-wrap gap-6">
                <div>
                    <p class="text-xs uppercase tracking-wide text-lentera-300">{{ __('ui.portal.level') }}</p>
                    <p class="text-lg font-semibold text-gold-light">{{ $level?->name ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-lentera-300">{{ __('ui.portal.points') }}</p>
                    <p class="tnum text-lg font-semibold">{{ number_format($balance, 0, ',', '.') }} <span class="text-sm font-normal text-lentera-200">≈ {{ $pointValue->mul($balance)->format() }}</span></p>
                </div>
            </div>
        </section>

        <section class="mb-6 rounded-2xl bg-white p-6 shadow-soft">
            <h2 class="mb-4 text-base font-semibold text-lentera-950">{{ __('ui.portal.orders') }}</h2>
            <div class="divide-y divide-gray-50">
                @forelse ($orders as $row)
                    <div class="flex items-center justify-between py-3">
                        <div>
                            <p class="tnum font-medium text-gray-800">{{ $row['sale']->number }}</p>
                            <p class="text-xs text-gray-500">{{ \Illuminate\Support\Carbon::parse($row['sale']->sale_date)->format('d/m/Y') }} · {{ \App\Support\Money::fromDecimal((string) $row['sale']->total_amount)->format() }}</p>
                        </div>
                        <span class="rounded-full bg-lentera-50 px-3 py-1 text-xs font-medium text-lentera-800">{{ __('ui.portal.stages.'.$row['stage']) }}</span>
                    </div>
                @empty
                    <p class="py-3 text-sm text-gray-500">{{ __('ui.portal.orders_empty') }}</p>
                @endforelse
            </div>
        </section>

        <section class="rounded-2xl bg-white p-6 shadow-soft">
            <h2 class="mb-4 text-base font-semibold text-lentera-950">{{ __('ui.portal.points_history') }}</h2>
            <div class="divide-y divide-gray-50">
                @forelse ($ledger as $row)
                    <div class="flex items-center justify-between py-2.5 text-sm">
                        <div>
                            <p class="font-medium text-gray-700">{{ __('ui.members.types.'.$row->type) }}</p>
                            <p class="text-xs text-gray-400">{{ $row->created_at->format('d/m/Y') }}</p>
                        </div>
                        <span class="tnum font-medium {{ $row->points < 0 ? 'text-red-600' : 'text-lentera-800' }}">{{ $row->points > 0 ? '+' : '' }}{{ number_format($row->points, 0, ',', '.') }}</span>
                    </div>
                @empty
                    <p class="py-3 text-sm text-gray-500">{{ __('ui.portal.points_empty') }}</p>
                @endforelse
            </div>
        </section>

        <footer class="mt-6 text-center text-xs text-gray-400">{{ __('ui.portal.footer') }}</footer>
    </div>
</body>
</html>
