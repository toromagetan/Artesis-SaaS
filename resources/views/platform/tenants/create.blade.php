<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-bold text-lentera-950">{{ __('ui.tenants.create_title') }}</h1>
    </x-slot>

    <div class="mx-auto max-w-3xl px-4 py-6 sm:px-6 lg:px-8">
        <div class="rounded-2xl bg-white p-5 shadow-soft ring-1 ring-black/5">
            <form method="POST" action="{{ route('platform.tenants.store') }}" class="space-y-4">
                @csrf
                <div>
                    <x-input-label for="name" :value="__('ui.tenants.name')" />
                    <x-text-input id="name" name="name" :value="old('name')" class="mt-1 block w-full" required />
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="email" :value="__('ui.tenants.email')" />
                    <x-text-input id="email" type="email" name="email" :value="old('email')" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('email')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="phone" :value="__('ui.tenants.phone')" />
                    <x-text-input id="phone" name="phone" :value="old('phone')" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('phone')" class="mt-1" />
                </div>
                <div class="flex items-center justify-end gap-2 pt-2">
                    <a href="{{ route('platform.tenants.index') }}" class="rounded-lg bg-white px-4 py-2 text-sm font-semibold text-gray-700 ring-1 ring-gray-200 hover:bg-gray-50">{{ __('ui.back') }}</a>
                    <x-primary-button>{{ __('ui.save') }}</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
