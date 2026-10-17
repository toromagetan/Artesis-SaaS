@php($rx = $prescription ?? null)
<div class="grid gap-4">
    <div class="grid grid-cols-2 gap-4">
        <div>
            <x-input-label for="examined_at" :value="__('ui.prescriptions.examined_at')" />
            <x-text-input id="examined_at" name="examined_at" type="date" class="mt-1 block w-full" :value="old('examined_at', $rx?->examined_at?->format('Y-m-d') ?? now()->format('Y-m-d'))" required />
            <x-input-error :messages="$errors->get('examined_at')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="examiner_id" :value="__('ui.prescriptions.examiner')" />
            <select id="examiner_id" name="examiner_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                <option value="">{{ __('ui.prescriptions.examiner_none') }}</option>
                @foreach ($examiners as $examiner)
                    <option value="{{ $examiner->id }}" @selected((string) old('examiner_id', $rx?->examiner_id) === (string) $examiner->id)>{{ $examiner->name }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('examiner_id')" class="mt-2" />
        </div>
    </div>

    @foreach (['od' => 'eye_right', 'os' => 'eye_left'] as $side => $labelKey)
        <fieldset class="rounded-lg border border-gray-200 p-4">
            <legend class="px-1 text-sm font-semibold text-lentera-900">{{ __('ui.prescriptions.'.$labelKey) }}</legend>
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <x-input-label :for="$side.'_sph'" :value="__('ui.prescriptions.sph')" />
                    <x-text-input :id="$side.'_sph'" name="{{ $side }}_sph" type="number" step="0.25" class="mt-1 block w-full tnum" :value="old($side.'_sph', $rx?->{$side.'_sph'} ?? '0.00')" required />
                    <x-input-error :messages="$errors->get($side.'_sph')" class="mt-2" />
                </div>
                <div>
                    <x-input-label :for="$side.'_cyl'" :value="__('ui.prescriptions.cyl')" />
                    <x-text-input :id="$side.'_cyl'" name="{{ $side }}_cyl" type="number" step="0.25" class="mt-1 block w-full tnum" :value="old($side.'_cyl', $rx?->{$side.'_cyl'} ?? '0.00')" required />
                    <x-input-error :messages="$errors->get($side.'_cyl')" class="mt-2" />
                </div>
                <div>
                    <x-input-label :for="$side.'_axis'" :value="__('ui.prescriptions.axis')" />
                    <x-text-input :id="$side.'_axis'" name="{{ $side }}_axis" type="number" min="0" max="180" class="mt-1 block w-full tnum" :value="old($side.'_axis', $rx?->{$side.'_axis'})" />
                    <x-input-error :messages="$errors->get($side.'_axis')" class="mt-2" />
                </div>
                <div>
                    <x-input-label :for="$side.'_add'" :value="__('ui.prescriptions.add')" />
                    <x-text-input :id="$side.'_add'" name="{{ $side }}_add" type="number" step="0.25" min="0" class="mt-1 block w-full tnum" :value="old($side.'_add', $rx?->{$side.'_add'})" />
                    <x-input-error :messages="$errors->get($side.'_add')" class="mt-2" />
                </div>
                <div>
                    <x-input-label :for="$side.($side === 'od' ? '_pdr' : '_pdl')" :value="__('ui.prescriptions.'.($side === 'od' ? 'pdr' : 'pdl'))" />
                    <x-text-input :id="$side.($side === 'od' ? '_pdr' : '_pdl')" name="{{ $side }}{{ $side === 'od' ? '_pdr' : '_pdl' }}" type="number" step="0.5" class="mt-1 block w-full tnum" :value="old($side.($side === 'od' ? '_pdr' : '_pdl'), $rx?->{$side.($side === 'od' ? '_pdr' : '_pdl')})" />
                    <x-input-error :messages="$errors->get($side.($side === 'od' ? '_pdr' : '_pdl'))" class="mt-2" />
                </div>
            </div>
        </fieldset>
    @endforeach

    <div class="grid grid-cols-2 gap-4">
        <div>
            <x-input-label for="pd_total" :value="__('ui.prescriptions.pd_total')" />
            <x-text-input id="pd_total" name="pd_total" type="number" step="0.5" class="mt-1 block w-full tnum" :value="old('pd_total', $rx?->pd_total)" />
            <x-input-error :messages="$errors->get('pd_total')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="prism" :value="__('ui.prescriptions.prism')" />
            <x-text-input id="prism" name="prism" class="mt-1 block w-full" :value="old('prism', $rx?->prism)" />
            <x-input-error :messages="$errors->get('prism')" class="mt-2" />
        </div>
    </div>
    <div>
        <x-input-label for="notes" :value="__('ui.prescriptions.notes')" />
        <textarea id="notes" name="notes" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500">{{ old('notes', $rx?->notes) }}</textarea>
        <x-input-error :messages="$errors->get('notes')" class="mt-2" />
    </div>
</div>
