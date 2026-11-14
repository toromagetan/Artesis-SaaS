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

            @include('platform.partials.billing')

            @if ($aiCredit !== null)
                <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <h3 class="text-sm font-semibold text-slate-700">{{ __('ui.ai_credits.title') }}</h3>
                        <p class="text-lg font-semibold text-emerald-700">
                            {{ number_format($aiCredit['balance']) }}
                            <span class="text-sm font-normal text-slate-500">{{ __('ui.ai_credits.questions') }}</span>
                        </p>
                    </div>
                    <p class="mt-2 text-sm text-slate-600">
                        {{ __('ui.ai_credits.price_note', ['price' => 'Rp '.number_format($aiCredit['price'], 0, ',', '.')]) }}
                    </p>

                    @if ($aiCredit['pending'] !== null)
                        <p class="mt-3 rounded-lg border border-gold/50 bg-gold/10 px-4 py-3 text-sm text-gold-dark">
                            {{ __('ui.ai_credits.pending_note', ['questions' => number_format($aiCredit['pending']->questions), 'amount' => 'Rp '.number_format($aiCredit['pending']->amount, 0, ',', '.')]) }}
                        </p>
                    @else
                        <form method="POST" action="{{ route('ai-credits.store') }}" class="mt-3 flex flex-wrap items-end gap-2">
                            @csrf
                            <div>
                                <x-input-label for="ai_questions" :value="__('ui.ai_credits.topup_label')" />
                                <x-text-input id="ai_questions" name="questions" type="number" min="10" max="10000" value="100" class="mt-1 block w-36" required />
                            </div>
                            <x-primary-button>{{ __('ui.ai_credits.topup_button') }}</x-primary-button>
                        </form>
                        @error('questions')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    @endif

                    @if ($aiCredit['ledger']->isNotEmpty())
                        <h4 class="mt-4 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('ui.ai_credits.history') }}</h4>
                        <ul class="mt-2 divide-y divide-slate-100 text-sm">
                            @foreach ($aiCredit['ledger'] as $entry)
                                <li class="flex items-center justify-between py-1.5">
                                    <span class="text-slate-600">
                                        {{ $entry->created_at->format('d/m/Y H:i') }} —
                                        {{ $entry->kind === 'topup' ? __('ui.ai_credits.kind_topup') : __('ui.ai_credits.kind_usage') }}
                                    </span>
                                    <span class="font-medium {{ $entry->questions_delta > 0 ? 'text-emerald-700' : 'text-slate-700' }}">
                                        {{ $entry->questions_delta > 0 ? '+' : '' }}{{ $entry->questions_delta }} → {{ number_format($entry->balance_after) }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endif
        @endif
    </div>
</x-app-layout>
