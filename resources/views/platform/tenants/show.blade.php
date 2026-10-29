<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h1 class="text-xl font-bold text-lentera-950">{{ $tenant->name }}</h1>
            <form method="POST" action="{{ route('platform.tenants.toggle', $tenant) }}">
                @csrf
                <button type="submit" class="rounded-lg px-4 py-2 text-sm font-semibold ring-1 {{ $tenant->is_active ? 'bg-white text-danger ring-red-200 hover:bg-red-50' : 'bg-lentera-800 text-white ring-lentera-800 hover:bg-lentera-700' }}">{{ $tenant->is_active ? __('ui.deactivate') : __('ui.activate') }}</button>
            </form>
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        @if (session('status'))
            <div class="rounded-lg bg-lentera-50 px-4 py-3 text-sm font-medium text-lentera-900 ring-1 ring-lentera-200">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-red-50 px-4 py-3 text-sm font-medium text-danger ring-1 ring-red-200">{{ $errors->first() }}</div>
        @endif

        @php $current = $tenant->currentSubscription(); @endphp

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="rounded-2xl bg-white p-5 shadow-soft ring-1 ring-black/5">
                <h2 class="text-sm font-semibold text-lentera-900">{{ __('ui.tenants.detail') }}</h2>
                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">{{ __('ui.tenants.slug') }}</dt><dd class="font-medium text-gray-800">{{ $tenant->slug }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">{{ __('ui.tenants.email') }}</dt><dd class="font-medium text-gray-800">{{ $tenant->email ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">{{ __('ui.tenants.phone') }}</dt><dd class="font-medium text-gray-800">{{ $tenant->phone ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">{{ __('ui.tenants.branches') }}</dt><dd class="font-medium text-gray-800">{{ $tenant->branches->count() }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">{{ __('ui.tenants.users') }}</dt><dd class="font-medium text-gray-800">{{ $tenant->users->count() }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">{{ __('ui.tenants.status') }}</dt><dd class="font-medium text-gray-800">{{ $tenant->is_active ? __('ui.tenants.active') : __('ui.tenants.inactive') }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">{{ __('ui.tenants.current_plan') }}</dt><dd class="font-medium text-gray-800">{{ $current?->plan->name ?? __('ui.tenants.no_plan') }}</dd></div>
                </dl>
            </div>

            <div class="rounded-2xl bg-white p-5 shadow-soft ring-1 ring-black/5">
                <h2 class="text-sm font-semibold text-lentera-900">{{ __('ui.tenants.assign') }}</h2>
                <form method="POST" action="{{ route('platform.tenants.assign-plan', $tenant) }}" class="mt-3 space-y-3">
                    @csrf
                    <div>
                        <x-input-label for="plan_id" :value="__('ui.tenants.assign_plan')" />
                        <select id="plan_id" name="plan_id" class="mt-1 block w-full rounded-lg border-gray-200 text-sm shadow-sm focus:border-lentera-500 focus:ring-lentera-500" required>
                            @foreach ($plans as $plan)
                                <option value="{{ $plan->id }}" @selected($current?->plan_id === $plan->id)>{{ $plan->name }} — Rp{{ number_format($plan->price_monthly, 0, ',', '.') }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="expires_at" :value="__('ui.tenants.assign_expires')" />
                        <x-text-input id="expires_at" type="date" name="expires_at" class="mt-1 block w-full" />
                    </div>
                    <div class="flex justify-end">
                        <x-primary-button>{{ __('ui.tenants.assign_save') }}</x-primary-button>
                    </div>
                </form>
            </div>

            <div class="rounded-2xl bg-white p-5 shadow-soft ring-1 ring-black/5">
                <h2 class="text-sm font-semibold text-lentera-900">{{ __('ui.tenants.override_add') }}</h2>
                <form method="POST" action="{{ route('platform.tenants.override', $tenant) }}" class="mt-3 space-y-3">
                    @csrf
                    <div>
                        <x-input-label for="feature_key" :value="__('ui.tenants.override_feature')" />
                        <select id="feature_key" name="feature_key" class="mt-1 block w-full rounded-lg border-gray-200 text-sm shadow-sm focus:border-lentera-500 focus:ring-lentera-500" required>
                            @foreach ($featureKinds as $key => $meta)
                                <option value="{{ $key }}">{{ __('ui.plans.features.'.$key) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="enabled" :value="__('ui.tenants.override_enabled')" />
                        <select id="enabled" name="enabled" class="mt-1 block w-full rounded-lg border-gray-200 text-sm shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                            <option value="1">{{ __('ui.tenants.value_on') }}</option>
                            <option value="0">{{ __('ui.tenants.value_off') }}</option>
                        </select>
                    </div>
                    <div>
                        <x-input-label for="limit_value" :value="__('ui.tenants.override_limit')" />
                        <x-text-input id="limit_value" type="number" min="0" name="limit_value" class="mt-1 block w-full" />
                    </div>
                    <div>
                        <x-input-label for="override_expires" :value="__('ui.tenants.override_expires')" />
                        <x-text-input id="override_expires" type="date" name="expires_at" class="mt-1 block w-full" />
                    </div>
                    <div>
                        <x-input-label for="reason" :value="__('ui.tenants.override_reason')" />
                        <x-text-input id="reason" name="reason" class="mt-1 block w-full" required />
                    </div>
                    <div class="flex justify-end">
                        <x-primary-button>{{ __('ui.tenants.override_save') }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl bg-white shadow-soft ring-1 ring-black/5">
            <h2 class="border-b border-gray-100 px-5 py-4 text-sm font-semibold text-lentera-900">{{ __('ui.tenants.effective') }}</h2>
            <table class="min-w-full divide-y divide-gray-100">
                <tbody class="divide-y divide-gray-50">
                    @php $overrideKeys = $tenant->overrides->where(fn ($o) => $o->expires_at === null || $o->expires_at->isFuture())->pluck('feature_key')->all(); @endphp
                    @foreach ($featureKinds as $key => $meta)
                        <tr class="text-sm">
                            <td class="px-5 py-2.5 font-medium text-gray-800">{{ __('ui.plans.features.'.$key) }}</td>
                            <td class="px-5 py-2.5">
                                @if ($meta['kind'] === 'boolean')
                                    {{ ($effectiveFeatures[$key]['enabled'] ?? false) ? __('ui.tenants.value_on') : __('ui.tenants.value_off') }}
                                @else
                                    {{ $effectiveFeatures[$key]['limit'] ?? __('ui.tenants.value_unlimited') }}
                                @endif
                            </td>
                            <td class="px-5 py-2.5 text-xs text-gray-500">{{ in_array($key, $overrideKeys, true) ? __('ui.tenants.source_override') : __('ui.tenants.source_plan') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="overflow-hidden rounded-2xl bg-white shadow-soft ring-1 ring-black/5">
                <h2 class="border-b border-gray-100 px-5 py-4 text-sm font-semibold text-lentera-900">{{ __('ui.tenants.subscriptions') }}</h2>
                <table class="min-w-full divide-y divide-gray-100">
                    <tbody class="divide-y divide-gray-50">
                        @forelse ($tenant->subscriptions as $sub)
                            <tr class="text-sm">
                                <td class="px-5 py-2.5 font-medium text-gray-800">{{ $sub->plan->name }}</td>
                                <td class="px-5 py-2.5">{{ $sub->starts_at->format('d/m/Y') }} → {{ $sub->expires_at?->format('d/m/Y') ?? __('ui.tenants.never') }}</td>
                                <td class="px-5 py-2.5 text-xs">{{ __('ui.tenants.sub_'.$sub->status) }}</td>
                            </tr>
                        @empty
                            <tr><td class="px-5 py-4 text-sm text-gray-500">{{ __('ui.tenants.no_plan') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="overflow-hidden rounded-2xl bg-white shadow-soft ring-1 ring-black/5">
                <h2 class="border-b border-gray-100 px-5 py-4 text-sm font-semibold text-lentera-900">{{ __('ui.tenants.overrides') }}</h2>
                <table class="min-w-full divide-y divide-gray-100">
                    <tbody class="divide-y divide-gray-50">
                        @forelse ($tenant->overrides as $override)
                            <tr class="text-sm">
                                <td class="px-5 py-2.5 font-medium text-gray-800">{{ __('ui.plans.features.'.$override->feature_key) }}</td>
                                <td class="px-5 py-2.5">
                                    @if ($override->enabled !== null)
                                        {{ $override->enabled ? __('ui.tenants.value_on') : __('ui.tenants.value_off') }}
                                    @else
                                        {{ $override->limit_value ?? __('ui.tenants.value_unlimited') }}
                                    @endif
                                    <span class="block text-xs text-gray-500">{{ $override->reason }} · {{ __('ui.tenants.expires') }}: {{ $override->expires_at?->format('d/m/Y') ?? __('ui.tenants.never') }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr><td class="px-5 py-4 text-sm text-gray-500">—</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
