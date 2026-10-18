
<div class="mb-4 flex flex-wrap gap-2 text-sm">
    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('reports.sales')): ?>
        <a href="<?php echo e(route('reports.sales')); ?>" class="rounded-lg px-3 py-1.5 <?php echo e(request()->routeIs('reports.sales') ? 'bg-lentera-700 text-white' : 'bg-white text-gray-600 shadow-soft hover:text-lentera-800'); ?>"><?php echo e(__('ui.reports.sales')); ?></a>
    <?php endif; ?>
    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('reports.purchases')): ?>
        <a href="<?php echo e(route('reports.purchases')); ?>" class="rounded-lg px-3 py-1.5 <?php echo e(request()->routeIs('reports.purchases') ? 'bg-lentera-700 text-white' : 'bg-white text-gray-600 shadow-soft hover:text-lentera-800'); ?>"><?php echo e(__('ui.reports.purchases')); ?></a>
        <a href="<?php echo e(route('reports.payables')); ?>" class="rounded-lg px-3 py-1.5 <?php echo e(request()->routeIs('reports.payables') ? 'bg-lentera-700 text-white' : 'bg-white text-gray-600 shadow-soft hover:text-lentera-800'); ?>"><?php echo e(__('ui.reports.payables')); ?></a>
    <?php endif; ?>
    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('reports.stock')): ?>
        <a href="<?php echo e(route('reports.stock')); ?>" class="rounded-lg px-3 py-1.5 <?php echo e(request()->routeIs('reports.stock') ? 'bg-lentera-700 text-white' : 'bg-white text-gray-600 shadow-soft hover:text-lentera-800'); ?>"><?php echo e(__('ui.reports.stock')); ?></a>
    <?php endif; ?>
    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('reports.financial')): ?>
        <a href="<?php echo e(route('financial.balance-sheet')); ?>" class="rounded-lg px-3 py-1.5 <?php echo e(request()->routeIs('financial.*') ? 'bg-lentera-700 text-white' : 'bg-white text-gray-600 shadow-soft hover:text-lentera-800'); ?>"><?php echo e(__('ui.financial.title')); ?></a>
    <?php endif; ?>
    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('reports.sales')): ?>
        <a href="<?php echo e(route('reports.analytics')); ?>" class="rounded-lg px-3 py-1.5 <?php echo e(request()->routeIs('reports.analytics') ? 'bg-lentera-700 text-white' : 'bg-white text-gray-600 shadow-soft hover:text-lentera-800'); ?>"><?php echo e(__('ui.analytics.title')); ?></a>
    <?php endif; ?>
</div>
<?php /**PATH /home/hatch/workspace/lentera/resources/views/reporting/partials/tabs.blade.php ENDPATH**/ ?>