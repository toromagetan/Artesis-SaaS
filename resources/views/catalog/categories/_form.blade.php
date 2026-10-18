<div class="grid gap-4">
    <div>
        <x-input-label for="name" :value="__('ui.categories.name')" />
        <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name', $category->name ?? '')" required />
        <x-input-error :messages="$errors->get('name')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="description" :value="__('ui.categories.description')" />
        <x-text-input id="description" name="description" class="mt-1 block w-full" :value="old('description', $category->description ?? '')" />
        <x-input-error :messages="$errors->get('description')" class="mt-2" />
    </div>
    <div class="grid grid-cols-2 gap-4">
        <div>
            <x-input-label for="type" :value="__('ui.categories.type')" />
            <select id="type" name="type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                @foreach (\App\Modules\Catalog\Models\Product::TYPES as $t)
                    <option value="{{ $t }}" @selected(old('type', $category->type ?? '') === $t)>{{ __('ui.products.types.'.$t) }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('type')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="sort_order" :value="__('ui.categories.sort_order')" />
            <x-text-input id="sort_order" name="sort_order" type="number" min="0" max="999" class="mt-1 block w-full tnum" :value="old('sort_order', $category->sort_order ?? 0)" />
            <x-input-error :messages="$errors->get('sort_order')" class="mt-2" />
        </div>
    </div>
</div>
