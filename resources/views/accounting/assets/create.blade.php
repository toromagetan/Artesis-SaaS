<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.assets.create') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
            <div class="mb-4">
                <a href="{{ route('assets.index') }}" class="text-sm text-gray-600 underline">{{ __('ui.back') }}</a>
            </div>
            <div class="rounded-lg bg-white p-6 shadow-soft">
                <form method="POST" action="{{ route('assets.store') }}" class="space-y-4">
                    @csrf
                    <div>
                        <x-input-label for="name" :value="__('ui.assets.name')" />
                        <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name')" required maxlength="255" />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <x-input-label for="acquired_at" :value="__('ui.assets.acquired_at')" />
                            <x-text-input id="acquired_at" name="acquired_at" type="date" class="mt-1 block w-full" :value="old('acquired_at')" required />
                            <x-input-error :messages="$errors->get('acquired_at')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="acquisition_cost" :value="__('ui.assets.cost')" />
                            <x-text-input id="acquisition_cost" name="acquisition_cost" type="number" step="0.01" min="0" class="mt-1 block w-full tnum" :value="old('acquisition_cost')" required />
                            <x-input-error :messages="$errors->get('acquisition_cost')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="useful_life_months" :value="__('ui.assets.life_months')" />
                            <x-text-input id="useful_life_months" name="useful_life_months" type="number" min="1" class="mt-1 block w-full tnum" :value="old('useful_life_months')" required />
                            <x-input-error :messages="$errors->get('useful_life_months')" class="mt-2" />
                        </div>
                    </div>
                    <p class="text-sm text-gray-600">{{ __('ui.assets.method_note') }}</p>
                    <div class="flex items-center justify-end gap-2 pt-2">
                        <x-primary-button>{{ __('ui.save') }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
