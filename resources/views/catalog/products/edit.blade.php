<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.products.edit') }} — {{ $product->name }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
            <div class="rounded-lg bg-white p-6 shadow-soft">
                <form method="POST" action="{{ route('products.update', $product) }}" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')
                    <div class="mb-4">
                        <x-input-label for="type" :value="__('ui.products.type')" />
                        <select id="type" name="type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500" @disabled($typeLocked)>
                            @foreach (\App\Modules\Catalog\Models\Product::TYPES as $t)
                                <option value="{{ $t }}" @selected(old('type', $product->type) === $t)>{{ __('ui.products.types.'.$t) }}</option>
                            @endforeach
                        </select>
                        @if ($typeLocked)
                            <input type="hidden" name="type" value="{{ $product->type }}">
                            <p class="mt-1 text-xs text-gray-400">{{ __('ui.products.type_locked') }}</p>
                        @endif
                        <x-input-error :messages="$errors->get('type')" class="mt-2" />
                    </div>
                    @include('catalog.products._form', ['type' => $product->type])
                    <div class="mt-6 flex items-center gap-3">
                        <a href="{{ route('products.show', $product) }}" class="text-sm text-gray-600 underline">{{ __('ui.back') }}</a>
                        <x-primary-button>{{ __('ui.save_changes') }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
