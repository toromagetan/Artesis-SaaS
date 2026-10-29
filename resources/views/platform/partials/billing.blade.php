{{-- M5: penagihan tenant — petunjuk transfer + permintaan naik paket. --}}
<section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
    <h3 class="text-sm font-semibold text-slate-700">{{ __('ui.billing.instructions_title') }}</h3>
    @if ($bankName && $bankAccount)
        <dl class="mt-3 space-y-1 text-sm text-slate-600">
            <div class="flex gap-2"><dt class="w-32 shrink-0 text-slate-500">{{ __('ui.billing.bank_name') }}</dt><dd class="font-medium text-slate-800">{{ $bankName }}</dd></div>
            <div class="flex gap-2"><dt class="w-32 shrink-0 text-slate-500">{{ __('ui.billing.bank_account') }}</dt><dd class="font-medium text-slate-800">{{ $bankAccount }}</dd></div>
            <div class="flex gap-2"><dt class="w-32 shrink-0 text-slate-500">{{ __('ui.billing.account_name') }}</dt><dd class="font-medium text-slate-800">{{ $accountName }}</dd></div>
        </dl>
    @else
        <p class="mt-2 text-sm text-slate-600">{{ __('ui.billing.instructions_default') }}</p>
    @endif
</section>

<section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
    <h3 class="text-sm font-semibold text-slate-700">{{ __('ui.billing.upgrade_title') }}</h3>

    @if (session('status'))
        <p class="mt-3 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{{ session('status') }}</p>
    @endif

    @if ($pendingRequest !== null)
        <div class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">
            <p class="font-medium">{{ __('ui.billing.pending_title') }}</p>
            <p class="mt-1">
                {{ $pendingRequest->plan?->name }} · {{ $pendingRequest->months }} ×
                Rp {{ number_format($pendingRequest->amount / max(1, $pendingRequest->months), 0, ',', '.') }}
                = <span class="font-semibold">Rp {{ number_format($pendingRequest->amount, 0, ',', '.') }}</span>
            </p>
        </div>
    @else
        <form method="POST" action="{{ route('billing.store') }}" class="mt-3 space-y-4">
            @csrf
            <div>
                <x-input-label for="billing-plan" :value="__('ui.billing.choose_plan')" />
                <select id="billing-plan" name="plan_id" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500" required>
                    @foreach ($paidPlans as $paidPlan)
                        <option value="{{ $paidPlan->id }}" @selected(old('plan_id') == $paidPlan->id)>
                            {{ $paidPlan->name }} — Rp {{ number_format($monthlyTotals[$paidPlan->id] ?? $paidPlan->price_monthly, 0, ',', '.') }}{{ __('ui.billing.per_month_short') }}
                        </option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('plan_id')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="billing-months" :value="__('ui.billing.months')" />
                <select id="billing-months" name="months" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500" required>
                    @foreach ([1, 3, 6, 12] as $monthsOption)
                        <option value="{{ $monthsOption }}" @selected(old('months', 1) == $monthsOption)>{{ $monthsOption }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('months')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="billing-note" :value="__('ui.billing.note')" />
                <x-text-input id="billing-note" name="note" type="text" class="mt-1 block w-full" :value="old('note')" />
                <x-input-error :messages="$errors->get('note')" class="mt-2" />
            </div>
            <x-primary-button>{{ __('ui.billing.submit') }}</x-primary-button>
        </form>
    @endif
</section>
