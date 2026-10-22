<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.suppliers.create') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-lg p-6">
                <form method="POST" action="{{ route('suppliers.store') }}">
                    @csrf
                    <div class="grid gap-4">
                        <div>
                            <x-input-label for="name" :value="__('ui.suppliers.name')" />
                            <x-text-input id="name" name="name" class="block mt-1 w-full" :value="old('name')" required />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="contact_person" :value="__('ui.suppliers.contact_person')" />
                            <x-text-input id="contact_person" name="contact_person" class="block mt-1 w-full" :value="old('contact_person')" />
                            <x-input-error :messages="$errors->get('contact_person')" class="mt-2" />
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <x-input-label for="phone" :value="__('ui.suppliers.phone')" />
                                <x-text-input id="phone" name="phone" class="block mt-1 w-full" :value="old('phone')" />
                                <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="email" :value="__('ui.suppliers.email')" />
                                <x-text-input id="email" name="email" type="email" class="block mt-1 w-full" :value="old('email')" />
                                <x-input-error :messages="$errors->get('email')" class="mt-2" />
                            </div>
                        </div>
                        <div>
                            <x-input-label for="address" :value="__('ui.suppliers.address')" />
                            <textarea id="address" name="address" rows="3" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500">{{ old('address') }}</textarea>
                            <x-input-error :messages="$errors->get('address')" class="mt-2" />
                        </div>
                    </div>
                    <div class="flex items-center gap-3 mt-6">
                        <a href="{{ route('suppliers.index') }}" class="text-sm text-gray-600 underline">{{ __('ui.back') }}</a>
                        <x-primary-button>{{ __('ui.save') }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
