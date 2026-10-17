
<form method="GET" action="<?php echo e(route('search')); ?>" class="w-full" role="search">
    <label class="relative block">
        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
            <svg class="h-4 w-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" /></svg>
        </span>
        <input type="search" name="q" value="<?php echo e(request('q')); ?>"
            placeholder="<?php echo e(__('ui.search_placeholder')); ?>"
            class="block w-full rounded-lg border-gray-300 bg-gray-50 py-2 pl-9 pr-3 text-sm shadow-soft placeholder:text-gray-400 focus:border-lentera-500 focus:bg-white focus:ring-lentera-500"
            autocomplete="off">
    </label>
</form>
<?php /**PATH /home/hatch/workspace/lentera/resources/views/components/lentera/global-search.blade.php ENDPATH**/ ?>