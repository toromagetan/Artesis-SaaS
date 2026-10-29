<x-app-layout>
    <x-slot name="header">
        <h2 class="text-lg font-semibold text-slate-800">{{ __('ui.plans.my_title') }}</h2>
    </x-slot>

    <div class="mx-auto max-w-3xl space-y-4 p-4 sm:p-6">
        @if ($tenant === null)
            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-base font-semibold text-slate-800">{{ __('ui.plans.my_standalone_title') }}</h3>
                <p class="mt-2 text-sm text-slate-600">{{ __('ui.plans.my_standalone_note') }}</p>
            </section>
        @else
            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <div>
                        <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('ui.plans.my_current') }}</p>
                        <h3 class="mt-1 text-xl font-bold text-slate-800">{{ $plan?->name ?? '—' }}</h3>
                    </div>
                    <p class="text-lg font-semibold text-emerald-700">
                        Rp {{ number_format((float) ($plan->price_monthly ?? 0), 0, ',', '.') }}
                        <span class="text-sm font-normal text-slate-500">{{ __('ui.plans.per_month') }}</span>
                    </p>
                </div>
                <p class="mt-2 text-sm text-slate-600">
                    {{ __('ui.plans.my_expires') }}:
                    {{ $subscription?->expires_at?->format('d/m/Y') ?? __('ui.plans.my_never') }}
                </p>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-sm font-semibold text-slate-700">{{ __('ui.plans.my_usage') }}</h3>
                <dl class="mt-3 space-y-2 text-sm">
                    @foreach (['products_max', 'users_max', 'branches_max'] as $key)
                        <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                            <dt class="text-slate-600">{{ __('ui.plans.features.'.$key) }}</dt>
                            <dd class="font-medium text-slate-800">
                                {{ $usage[$key] }} / {{ $limits[$key] ?? __('ui.plans.unlimited') }}
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-sm font-semibold text-slate-700">{{ __('ui.plans.my_features') }}</h3>
                <ul class="mt-3 grid gap-2 sm:grid-cols-2">
                    @foreach ($registry as $key => $definition)
                        @continue($definition['kind'] !== 'boolean')
                        <li class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-sm">
                            <span class="text-slate-700">{{ __('ui.plans.features.'.$key) }}</span>
                            @if (! empty($features[$key]['enabled']))
                                <span class="font-medium text-emerald-700">{{ __('ui.plans.active') }}</span>
                            @else
                                <span class="font-medium text-slate-400">{{ __('ui.plans.inactive') }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
                <p class="mt-4 text-sm text-slate-500">{{ __('ui.plans.my_note') }}</p>
            </section>
        @endif
    </div>
</x-app-layout>
