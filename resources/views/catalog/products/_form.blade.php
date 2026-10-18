<div class="grid gap-4">
    <div>
        <x-input-label for="parent_id" :value="__('ui.products.parent')" />
        <select id="parent_id" name="parent_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
            <option value="">{{ __('ui.products.parent_auto') }}</option>
            @foreach ($parents as $p)
                <option value="{{ $p->id }}" @selected((int) old('parent_id', $product->parent_id ?? ($parent->id ?? 0)) === $p->id)>{{ $p->name }}{{ $p->brand ? ' · '.$p->brand : '' }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('parent_id')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="sku" :value="__('ui.products.sku')" />
        <x-text-input id="sku" name="sku" class="mt-1 block w-full" :value="old('sku', $product->sku ?? '')" required />
        <x-input-error :messages="$errors->get('sku')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="barcode" :value="__('ui.products.barcode')" />
        <x-text-input id="barcode" name="barcode" class="mt-1 block w-full" :value="old('barcode', $product->barcode ?? '')" maxlength="50" />
        <x-input-error :messages="$errors->get('barcode')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="name" :value="__('ui.products.name')" />
        <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name', $product->name ?? ($parent->name ?? ''))" required />
        <x-input-error :messages="$errors->get('name')" class="mt-2" />
    </div>
    <div class="grid grid-cols-2 gap-4">
        <div>
            <x-input-label for="brand" :value="__('ui.products.brand_optional')" />
            <x-text-input id="brand" name="brand" class="mt-1 block w-full" :value="old('brand', $product->brand ?? ($parent->brand ?? ''))" />
            <x-input-error :messages="$errors->get('brand')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="color" :value="__('ui.products.color_optional')" />
            <x-text-input id="color" name="color" class="mt-1 block w-full" :value="old('color', $product->color ?? '')" />
            <x-input-error :messages="$errors->get('color')" class="mt-2" />
        </div>
    </div>
    @if ($type === 'lens')
        <div class="grid grid-cols-4 gap-4">
            <div>
                <x-input-label for="sph" value="SPH" />
                <x-text-input id="sph" name="sph" type="number" step="0.25" min="-30" max="30" class="mt-1 block w-full tnum" :value="old('sph', $product->sph ?? '')" />
                <x-input-error :messages="$errors->get('sph')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="cyl" value="CYL" />
                <x-text-input id="cyl" name="cyl" type="number" step="0.25" min="-30" max="30" class="mt-1 block w-full tnum" :value="old('cyl', $product->cyl ?? '')" />
                <x-input-error :messages="$errors->get('cyl')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="add" value="ADD" />
                <x-text-input id="add" name="add" type="number" step="0.25" min="0" max="4" class="mt-1 block w-full tnum" :value="old('add', $product->add ?? '')" />
                <x-input-error :messages="$errors->get('add')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="eye_side" :value="__('ui.products.eye_side')" />
                <select id="eye_side" name="eye_side" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                    <option value="">—</option>
                    <option value="R" @selected(old('eye_side', $product->eye_side ?? '') === 'R')>R (OD)</option>
                    <option value="L" @selected(old('eye_side', $product->eye_side ?? '') === 'L')>L (OS)</option>
                </select>
                <x-input-error :messages="$errors->get('eye_side')" class="mt-2" />
            </div>
        </div>
    @endif
    @if ($type === 'softlens')
        <div>
            <x-input-label for="power" :value="__('ui.products.power')" />
            <x-text-input id="power" name="power" type="number" step="0.25" min="-30" max="30" class="mt-1 block w-full tnum" :value="old('power', $product->power ?? '')" />
            <x-input-error :messages="$errors->get('power')" class="mt-2" />
        </div>
    @endif
    <div class="grid grid-cols-2 gap-4">
        <div>
            <x-input-label for="cost_price" :value="__('ui.products.cost_price')" />
            <x-text-input id="cost_price" name="cost_price" type="number" step="0.01" min="0" class="mt-1 block w-full tnum" :value="old('cost_price', $product->cost_price ?? '')" required />
            <x-input-error :messages="$errors->get('cost_price')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="sale_price" :value="__('ui.products.sale_price')" />
            <x-text-input id="sale_price" name="sale_price" type="number" step="0.01" min="0" class="mt-1 block w-full tnum" :value="old('sale_price', $product->sale_price ?? '')" required />
            <x-input-error :messages="$errors->get('sale_price')" class="mt-2" />
        </div>
    </div>
    @foreach ($attributeKeys as $key)
        <div>
            <x-input-label :for="'attr_'.$key" :value="__('ui.products.attributes.'.$key)" />
            @if ($key === 'expiry_tracked')
                <label class="mt-2 inline-flex items-center gap-2 text-sm">
                    <input type="checkbox" name="attributes[{{ $key }}]" value="1" class="rounded border-gray-300" @checked(old('attributes.'.$key, isset($product) ? $product->attributeValue('expiry_tracked') : null))>
                    {{ __('ui.yes') }}
                </label>
            @else
                <x-text-input :id="'attr_'.$key" name="attributes[{{ $key }}]" class="mt-1 block w-full" :value="old('attributes.'.$key, isset($product) ? $product->attributeValue($key) : '')" />
            @endif
        </div>
    @endforeach
    <x-input-error :messages="$errors->get('attributes')" class="mt-2" />
    <div>
        <x-input-label for="image" :value="__('ui.products.image')" />
        @if (! empty($product?->imageUrl()))
            <img src="{{ $product->imageUrl() }}" alt="" class="mt-2 h-24 w-24 rounded object-cover">
            <label class="mt-2 flex items-center gap-2 text-sm text-gray-600">
                <input type="checkbox" name="image_remove" value="1" class="rounded border-gray-300"> {{ __('ui.products.image_remove') }}
            </label>
        @endif
        <input id="image" name="image" type="file" accept="image/*" class="mt-2 block w-full text-sm">
        <x-input-error :messages="$errors->get('image')" class="mt-2" />
    </div>
</div>
