<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.products.create') }} — {{ __('ui.products.types.'.$type) }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
            <div class="rounded-lg bg-white p-6 shadow-soft">
                <form method="POST" action="{{ route('products.store', $type) }}">
                    @csrf
                    <div class="grid gap-4">
                        <div>
                            <x-input-label for="sku" :value="__('ui.products.sku')" />
                            <x-text-input id="sku" name="sku" class="mt-1 block w-full" :value="old('sku')" required />
                            <x-input-error :messages="$errors->get('sku')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="barcode" :value="__('ui.products.barcode')" />
                            <x-text-input id="barcode" name="barcode" class="mt-1 block w-full" :value="old('barcode')" maxlength="50" />
                            <x-input-error :messages="$errors->get('barcode')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="name" :value="__('ui.products.name')" />
                            <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name')" required />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="brand" :value="__('ui.products.brand_optional')" />
                                <x-text-input id="brand" name="brand" class="mt-1 block w-full" :value="old('brand')" />
                                <x-input-error :messages="$errors->get('brand')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="color" :value="__('ui.products.color_optional')" />
                                <x-text-input id="color" name="color" class="mt-1 block w-full" :value="old('color')" />
                                <x-input-error :messages="$errors->get('color')" class="mt-2" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="cost_price" :value="__('ui.products.cost_price')" />
                                <x-text-input id="cost_price" name="cost_price" type="number" step="0.01" min="0" class="mt-1 block w-full tnum" :value="old('cost_price')" required />
                                <x-input-error :messages="$errors->get('cost_price')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="sale_price" :value="__('ui.products.sale_price')" />
                                <x-text-input id="sale_price" name="sale_price" type="number" step="0.01" min="0" class="mt-1 block w-full tnum" :value="old('sale_price')" required />
                                <x-input-error :messages="$errors->get('sale_price')" class="mt-2" />
                            </div>
                        </div>
                        @foreach ($attributeKeys as $key)
                            <div>
                                <x-input-label :for="'attr_'.$key" :value="__('ui.products.attributes.'.$key)" />
                                @if ($key === 'expiry_tracked')
                                    <label class="mt-2 inline-flex items-center gap-2 text-sm">
                                        <input type="checkbox" name="attributes[{{ $key }}]" value="1" class="rounded border-gray-300" @checked(old('attributes.'.$key))>
                                        {{ __('ui.yes') }}
                                    </label>
                                @else
                                    <x-text-input :id="'attr_'.$key" name="attributes[{{ $key }}]" class="mt-1 block w-full" :value="old('attributes.'.$key)" />
                                @endif
                            </div>
                        @endforeach
                        <x-input-error :messages="$errors->get('attributes')" class="mt-2" />
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
