<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.settings_general.title') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-lg border border-lentera-200 bg-lentera-50 px-4 py-3 text-sm text-lentera-800">{{ session('status') }}</div>
            @endif

            <div class="bg-white shadow-sm rounded-lg p-6">
                <form method="POST" action="{{ route('settings.general.update') }}">
                    @csrf
                    @method('PUT')

                    <h2 class="text-sm font-semibold text-lentera-950">{{ __('ui.settings_general.store_section') }}</h2>
                    <div class="mt-3 grid gap-4">
                        <div>
                            <x-input-label for="store_name" :value="__('ui.settings_general.store_name')" />
                            <x-text-input id="store_name" name="store_name" class="block mt-1 w-full" :value="old('store_name', $values['store.name'])" required />
                            <x-input-error :messages="$errors->get('store_name')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="store_address" :value="__('ui.settings_general.store_address')" />
                            <x-text-input id="store_address" name="store_address" class="block mt-1 w-full" :value="old('store_address', $values['store.address'])" />
                            <x-input-error :messages="$errors->get('store_address')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="store_phone" :value="__('ui.settings_general.store_phone')" />
                            <x-text-input id="store_phone" name="store_phone" class="block mt-1 w-full" :value="old('store_phone', $values['store.phone'])" />
                            <x-input-error :messages="$errors->get('store_phone')" class="mt-2" />
                        </div>
                    </div>

                    <h2 class="mt-8 text-sm font-semibold text-lentera-950">{{ __('ui.settings_general.tax_section') }}</h2>
                    <div class="mt-3 grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="tax_rate_default" :value="__('ui.settings_general.tax_rate')" />
                            <x-text-input id="tax_rate_default" name="tax_rate_default" type="number" min="0" max="100" step="0.01" class="block mt-1 w-full tnum" :value="old('tax_rate_default', $values['tax.rate_default'])" required />
                            <x-input-error :messages="$errors->get('tax_rate_default')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="tax_mode" :value="__('ui.settings_general.tax_mode')" />
                            <select id="tax_mode" name="tax_mode" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                                <option value="exclusive" @selected(old('tax_mode', $values['tax.mode']) === 'exclusive')>{{ __('ui.settings_general.tax_exclusive') }}</option>
                                <option value="inclusive" @selected(old('tax_mode', $values['tax.mode']) === 'inclusive')>{{ __('ui.settings_general.tax_inclusive') }}</option>
                            </select>
                            <x-input-error :messages="$errors->get('tax_mode')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="discount_threshold" :value="__('ui.settings_general.discount_threshold')" />
                            <x-text-input id="discount_threshold" name="discount_threshold" type="number" min="0" max="100" step="0.01" class="block mt-1 w-full tnum" :value="old('discount_threshold', $values['sales.discount_threshold_percent'])" required />
                            <p class="mt-1 text-xs text-gray-500">{{ __('ui.settings_general.discount_threshold_hint') }}</p>
                            <x-input-error :messages="$errors->get('discount_threshold')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="technician_commission_rate" :value="__('ui.settings_general.technician_rate')" />
                            <x-text-input id="technician_commission_rate" name="technician_commission_rate" type="number" min="0" max="100" step="0.01" class="block mt-1 w-full tnum" :value="old('technician_commission_rate', $values['lab.technician_commission_rate'])" required />
                            <x-input-error :messages="$errors->get('technician_commission_rate')" class="mt-2" />
                        </div>
                    </div>

                    <div class="mt-6">
                        <x-primary-button>{{ __('ui.save') }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
