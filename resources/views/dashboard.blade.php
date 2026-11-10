<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.dashboard.title') }}</h1>
    </x-slot>

    <div id="dash-scope" class="dash-glass relative py-8" x-data="dashboardDinamis('{{ $period }}')" x-init="mulai()">
        <div aria-hidden="true" class="pointer-events-none absolute -top-10 left-[8%] h-64 w-64 rounded-full bg-lentera-300/40 blur-3xl"></div>
        <div aria-hidden="true" class="pointer-events-none absolute right-[4%] top-24 h-56 w-56 rounded-full bg-gold-light/30 blur-3xl"></div>
        <div class="relative mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div class="inline-flex rounded-lg bg-white p-1 shadow-soft">
                    @foreach (['today' => 'period_today', '7d' => 'period_7d', '30d' => 'period_30d', 'month' => 'period_month'] as $key => $labelKey)
                        <button type="button" @click="aturPeriode('{{ $key }}')"
                            :class="periode === '{{ $key }}' ? 'bg-lentera-700 text-white' : 'text-gray-500 hover:text-lentera-800'"
                            class="rounded-md px-3 py-1.5 text-sm font-medium transition duration-150">
                            {{ __('ui.dashboard.'.$labelKey) }}
                        </button>
                    @endforeach
                </div>
                <p class="text-xs text-gray-400">{{ __('ui.dashboard.updated_at') }} <span class="tnum" x-text="diperbarui">{{ now()->format('H:i:s') }}</span></p>
            </div>

            <div id="dashboard-cards">
                @include('dashboard.partials.cards')
            </div>

            <div id="dashboard-charts" class="mt-4">
                @include('dashboard.partials.charts')
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('dashboardDinamis', (awal) => ({
                periode: awal,
                diperbarui: '{{ now()->format('H:i:s') }}',
                mulai() {
                    // Animasi masuk kaca hanya untuk muat pertama: sesudah
                    // ±1,4 dtk pembungkus ditandai .entered agar refresh
                    // berkala tidak memutar ulang animasi masuk.
                    setTimeout(() => document.getElementById('dash-scope')?.classList.add('entered'), 1400);
                    setInterval(() => this.muat(), 60000);
                },
                aturPeriode(p) {
                    this.periode = p;
                    this.muat();
                },
                async muat() {
                    const scope = document.getElementById('dash-scope');
                    scope?.classList.add('refreshing');
                    try {
                        const r = await fetch('/dashboard/data?periode=' + this.periode, { headers: { 'Accept': 'application/json' } });
                        if (!r.ok) return;
                        const d = await r.json();
                        document.getElementById('dashboard-cards').innerHTML = d.cards_html;
                        document.getElementById('dashboard-charts').innerHTML = d.charts_html;
                        this.diperbarui = d.updated_at;
                    } catch (e) { /* abaikan kegagalan sesaat; interval berikutnya mencoba lagi */ }
                    // Konten baru masuk dari buram ke tajam (crossfade singkat).
                    requestAnimationFrame(() => requestAnimationFrame(() => scope?.classList.remove('refreshing')));
                },
            }));
        });
    </script>
</x-app-layout>
