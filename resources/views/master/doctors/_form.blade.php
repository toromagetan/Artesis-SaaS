<div class="grid gap-4">
    <div>
        <x-input-label for="name" :value="__('ui.doctors.name')" />
        <x-text-input id="name" name="name" class="block mt-1 w-full" :value="old('name', $doctor->name ?? '')" required />
        <x-input-error :messages="$errors->get('name')" class="mt-2" />
    </div>
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <x-input-label for="clinic" :value="__('ui.doctors.clinic')" />
            <x-text-input id="clinic" name="clinic" class="block mt-1 w-full" :value="old('clinic', $doctor->clinic ?? '')" />
            <x-input-error :messages="$errors->get('clinic')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="phone" :value="__('ui.doctors.phone')" />
            <x-text-input id="phone" name="phone" class="block mt-1 w-full" :value="old('phone', $doctor->phone ?? '')" />
            <x-input-error :messages="$errors->get('phone')" class="mt-2" />
        </div>
    </div>
    <div>
        <x-input-label for="commission_rate" :value="__('ui.doctors.commission_rate')" />
        <x-text-input id="commission_rate" name="commission_rate" type="number" min="0" max="100" step="0.01" class="block mt-1 w-full tnum" :value="old('commission_rate', $doctor->commission_rate ?? '')" required />
        <p class="mt-1 text-xs text-gray-500">{{ __('ui.doctors.commission_rate_hint') }}</p>
        <x-input-error :messages="$errors->get('commission_rate')" class="mt-2" />
    </div>
</div>
