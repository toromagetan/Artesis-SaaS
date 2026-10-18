<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.products.parent_create') }} — {{ __('ui.products.types.'.$type) }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
            <div class="rounded-lg bg-white p-6 shadow-soft">
                <form method="POST" action="{{ route('products.parents.store', $type) }}">
                    @csrf
                    <div class="grid gap-4">
                        <div>
                            <x-input-label for="name" :value="__('ui.products.name')" />
                            <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name')" required />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="brand" :value="__('ui.products.brand_optional')" />
                            <x-text-input id="brand" name="brand" class="mt-1 block w-full" :value="old('brand')" />
                            <x-input-error :messages="$errors->get('brand')" class="mt-2" />
                        </div>
                    </div>
                    <div class="mt-6 flex items-center gap-3">
                        <a href="{{ route('products.index', $type) }}" class="text-sm text-gray-600 underline">{{ __('ui.back') }}</a>
                        <x-primary-button>{{ __('ui.save') }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
