<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['label' => null]));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter((['label' => null]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<div x-data="{ open: false }" class="relative inline-block text-left" data-lentera="dropdown-aksi">
    <button type="button" @click="open = !open"
        class="inline-flex items-center gap-1.5 rounded-lg bg-lentera-700 px-4 py-2 text-sm font-semibold text-white shadow-soft transition duration-150 hover:bg-lentera-800 focus:outline-none focus:ring-2 focus:ring-lentera-500">
        <?php echo e($label ?? __('ui.actions')); ?>

        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
    </button>
    <div x-show="open" @click.away="open = false" x-cloak
        class="absolute right-0 z-20 mt-2 w-56 rounded-lg bg-white py-1 shadow-soft ring-1 ring-gray-900/5">
        <?php echo e($slot); ?>

    </div>
</div>
<?php /**PATH /home/hatch/workspace/lentera/resources/views/components/lentera/dropdown-aksi.blade.php ENDPATH**/ ?>