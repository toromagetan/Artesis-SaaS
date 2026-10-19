<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.insurances.create') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-lg p-6">
                <form method="POST" action="{{ route('insurances.store') }}">
                    @csrf
                    <div class="grid gap-4">
                        <div>
                            <x-input-label for="name" :value="__('ui.insurances.name')" />
                            <x-text-input id="name" name="name" class="block mt-1 w-full" :value="old('name')" required />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="claim_value" :value="__('ui.insurances.claim_value')" />
                            <x-text-input id="claim_value" name="claim_value" type="number" min="0" step="0.01" class="block mt-1 w-full tnum" :value="old('claim_value')" required />
                            <p class="mt-1 text-xs text-gray-500">{{ __('ui.insurances.claim_value_hint') }}</p>
                            <x-input-error :messages="$errors->get('claim_value')" class="mt-2" />
                        </div>
                    </div>
                    <div class="flex items-center gap-3 mt-6">
                        <a href="{{ route('insurances.index') }}" class="text-sm text-gray-600 underline">{{ __('ui.back') }}</a>
                        <x-primary-button>{{ __('ui.save') }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
