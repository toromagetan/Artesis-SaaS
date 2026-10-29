<x-app-layout>
    <x-slot name="header">
        <h2 class="text-lg font-semibold text-slate-800">{{ __('ui.billing.title') }}</h2>
    </x-slot>

    <div class="mx-auto max-w-5xl space-y-4 p-4 sm:p-6">
        @if (session('status'))
            <p class="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{{ session('status') }}</p>
        @endif

        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-700">{{ __('ui.billing.settings_title') }}</h3>
            <form method="POST" action="{{ route('platform.billing.settings') }}" class="mt-3 grid gap-4 sm:grid-cols-3">
                @csrf
                @method('PUT')
                <div>
                    <x-input-label for="billing_bank_name" :value="__('ui.billing.bank_name')" />
                    <x-text-input id="billing_bank_name" name="billing_bank_name" type="text" class="mt-1 block w-full" :value="old('billing_bank_name', $bankName)" />
                </div>
                <div>
                    <x-input-label for="billing_bank_account" :value="__('ui.billing.bank_account')" />
                    <x-text-input id="billing_bank_account" name="billing_bank_account" type="text" class="mt-1 block w-full" :value="old('billing_bank_account', $bankAccount)" />
                </div>
                <div>
                    <x-input-label for="billing_account_name" :value="__('ui.billing.account_name')" />
                    <x-text-input id="billing_account_name" name="billing_account_name" type="text" class="mt-1 block w-full" :value="old('billing_account_name', $accountName)" />
                </div>
                <div class="sm:col-span-3">
                    <x-primary-button>{{ __('ui.billing.save') }}</x-primary-button>
                </div>
            </form>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-700">{{ __('ui.billing.requests_title') }}</h3>
            <div class="mt-3 overflow-x-auto">
                <table class="w-full min-w-[640px] text-left text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-slate-500">
                            <th class="py-2 pr-3 font-medium">{{ __('ui.billing.tenant') }}</th>
                            <th class="py-2 pr-3 font-medium">{{ __('ui.billing.plan') }}</th>
                            <th class="py-2 pr-3 font-medium">{{ __('ui.billing.amount') }}</th>
                            <th class="py-2 pr-3 font-medium">{{ __('ui.billing.status') }}</th>
                            <th class="py-2 pr-3 font-medium">{{ __('ui.billing.created_at') }}</th>
                            <th class="py-2 font-medium">{{ __('ui.billing.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($requests as $billingRequest)
                            <tr class="border-b border-slate-100">
                                <td class="py-2 pr-3">
                                    <span class="font-medium text-slate-800">{{ $billingRequest->tenant?->name }}</span>
                                    <span class="block text-xs text-slate-500">{{ __('ui.billing.branches_count', ['count' => $billingRequest->extra_branches + 1]) }}</span>
                                </td>
                                <td class="py-2 pr-3">{{ $billingRequest->plan?->name }} · {{ __('ui.billing.months_count', ['count' => $billingRequest->months]) }}</td>
                                <td class="py-2 pr-3 font-medium">Rp {{ number_format($billingRequest->amount, 0, ',', '.') }}</td>
                                <td class="py-2 pr-3">{{ __('ui.billing.status_'.$billingRequest->status) }}</td>
                                <td class="py-2 pr-3 text-slate-500">{{ $billingRequest->created_at?->format('d/m/Y H:i') }}</td>
                                <td class="py-2">
                                    @if ($billingRequest->isPending())
                                        <div class="flex gap-2">
                                            <form method="POST" action="{{ route('platform.billing.confirm', $billingRequest) }}">
                                                @csrf
                                                <button type="submit" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-500">{{ __('ui.billing.confirm') }}</button>
                                            </form>
                                            <form method="POST" action="{{ route('platform.billing.reject', $billingRequest) }}">
                                                @csrf
                                                <button type="submit" class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-200">{{ __('ui.billing.reject') }}</button>
                                            </form>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-4">
                                    <x-lentera.empty-state :message="__('ui.empty')" />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-app-layout>
