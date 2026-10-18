
<div x-data="{ open: false }" class="relative inline-block text-left" data-lentera="row-actions">
    <button type="button" @click="open = !open"
        class="inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 shadow-soft transition duration-150 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-lentera-500">
        <?php echo e(__('ui.actions')); ?>

        <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
    </button>
    <div x-show="open" @click.away="open = false" x-cloak
        class="absolute right-0 z-20 mt-2 w-52 rounded-lg bg-white py-1 shadow-soft ring-1 ring-gray-900/5">
        <?php echo e($slot); ?>

    </div>
</div>
<?php /**PATH /home/hatch/workspace/lentera/resources/views/components/lentera/row-actions.blade.php ENDPATH**/ ?>