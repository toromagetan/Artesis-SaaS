<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-bold text-lentera-950">{{ __('ui.plans.title') }}</h1>
    </x-slot>

    <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        @if (session('status'))
            <div class="mb-4 rounded-lg bg-lentera-50 px-4 py-3 text-sm font-medium text-lentera-900 ring-1 ring-lentera-200">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded-lg bg-red-50 px-4 py-3 text-sm font-medium text-danger ring-1 ring-red-200">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('platform.plans.update') }}" class="space-y-6">
            @csrf
            @method('PUT')

            @forelse ($plans as $plan)
                <div class="overflow-hidden rounded-2xl bg-white shadow-soft ring-1 ring-black/5">
                    <div class="border-b border-gray-100 px-5 py-4">
                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <div>
                                <x-input-label for="plan-name-{{ $plan->id }}" :value="__('ui.plans.name')" />
                                <x-text-input id="plan-name-{{ $plan->id }}" name="plans[{{ $plan->id }}][name]" :value="$plan->name" class="mt-1 block w-full" required />
                            </div>
                            <div>
                                <x-input-label for="plan-price-{{ $plan->id }}" :value="__('ui.plans.price_monthly')" />
                                <x-text-input id="plan-price-{{ $plan->id }}" type="number" min="0" name="plans[{{ $plan->id }}][price_monthly]" :value="$plan->price_monthly" class="mt-1 block w-full" required />
                            </div>
                            <div>
                                <x-input-label for="plan-extra-{{ $plan->id }}" :value="__('ui.plans.extra_branch_price')" />
                                <x-text-input id="plan-extra-{{ $plan->id }}" type="number" min="0" name="plans[{{ $plan->id }}][extra_branch_price]" :value="$plan->extra_branch_price" class="mt-1 block w-full" required />
                            </div>
                            <div class="flex items-end pb-1">
                                <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700">
                                    <input type="checkbox" name="plans[{{ $plan->id }}][is_active]" value="1" @checked($plan->is_active) class="rounded border-gray-300 text-lentera-700 focus:ring-lentera-500">
                                    {{ __('ui.plans.active') }}
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="px-5 py-4">
                        <h3 class="text-sm font-semibold text-lentera-900">{{ __('ui.plans.features_heading') }}</h3>
                        <p class="mt-0.5 text-xs text-gray-500">{{ __('ui.plans.unlimited_hint') }}</p>
                        @php $map = $plan->featuresMap(); @endphp
                        <div class="mt-3 grid gap-x-8 gap-y-2.5 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($featureKinds as $key => $meta)
                                @if ($meta['kind'] === 'boolean')
                                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                        <input type="checkbox" name="plans[{{ $plan->id }}][features][{{ $key }}]" value="1" @checked($map[$key]['enabled'] ?? false) class="rounded border-gray-300 text-lentera-700 focus:ring-lentera-500">
                                        {{ __('ui.plans.features.'.$key) }}
                                    </label>
                                @else
                                    <div class="flex items-center justify-between gap-3">
                                        <label for="feat-{{ $plan->id }}-{{ $key }}" class="text-sm text-gray-700">{{ __('ui.plans.features.'.$key) }}</label>
                                        <input id="feat-{{ $plan->id }}-{{ $key }}" type="number" min="0" name="plans[{{ $plan->id }}][features][{{ $key }}]" value="{{ $map[$key]['limit'] ?? '' }}" placeholder="∞" class="w-24 rounded-lg border-gray-200 text-sm shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>
            @empty
                <div class="rounded-2xl bg-white px-5 py-8 shadow-soft ring-1 ring-black/5"><x-lentera.empty-state :message="__('ui.empty')" /></div>
            @endforelse

            <div class="flex justify-end">
                <x-primary-button>{{ __('ui.plans.save') }}</x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
