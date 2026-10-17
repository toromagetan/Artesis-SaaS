<?php if (isset($component)) { $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54 = $attributes; } ?>
<?php $component = App\View\Components\AppLayout::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('app-layout'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\AppLayout::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
     <?php $__env->slot('header', null, []); ?> 
        <h1 class="text-xl font-semibold leading-tight text-lentera-950"><?php echo e(__('ui.reports.sales')); ?></h1>
     <?php $__env->endSlot(); ?>

    <div class="py-8">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <?php echo $__env->make('reporting.partials.tabs', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            <?php echo $__env->make('reporting.partials.filters', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

            <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.reports.branch')); ?></th>
                            <th class="px-4 py-3 text-right font-medium"><?php echo e(__('ui.reports.transactions')); ?></th>
                            <th class="px-4 py-3 text-right font-medium"><?php echo e(__('ui.reports.total')); ?></th>
                            <th class="px-4 py-3 text-right font-medium"><?php echo e(__('ui.reports.returns')); ?></th>
                            <th class="px-4 py-3 text-right font-medium"><?php echo e(__('ui.reports.net')); ?></th>
                            <th class="px-4 py-3 text-right font-medium"><?php echo e(__('ui.reports.paid')); ?></th>
                            <th class="px-4 py-3 text-right font-medium"><?php echo e(__('ui.reports.receivable')); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 font-medium text-gray-800"><?php echo e(\App\Modules\Master\Models\Branch::find($row['branch_id'])?->name); ?></td>
                                <td class="px-4 py-3 text-right tnum"><?php echo e($row['transactions']); ?></td>
                                <td class="px-4 py-3 text-right tnum"><?php echo e($row['total']->format()); ?></td>
                                <td class="px-4 py-3 text-right tnum"><?php echo e($row['returns']->format()); ?></td>
                                <td class="px-4 py-3 text-right font-medium tnum"><?php echo e($row['net']->format()); ?></td>
                                <td class="px-4 py-3 text-right tnum"><?php echo e($row['paid']->format()); ?></td>
                                <td class="px-4 py-3 text-right tnum"><?php echo e($row['receivable']->format()); ?></td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        <tr class="bg-gray-50 font-medium">
                            <td class="px-4 py-3">TOTAL</td>
                            <td class="px-4 py-3 text-right tnum"><?php echo e($totals['transactions']); ?></td>
                            <td class="px-4 py-3 text-right tnum"><?php echo e($totals['total']->format()); ?></td>
                            <td class="px-4 py-3 text-right tnum"><?php echo e($totals['returns']->format()); ?></td>
                            <td class="px-4 py-3 text-right tnum"><?php echo e($totals['net']->format()); ?></td>
                            <td class="px-4 py-3 text-right tnum"><?php echo e($totals['paid']->format()); ?></td>
                            <td class="px-4 py-3 text-right tnum"><?php echo e($totals['receivable']->format()); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $attributes = $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $component = $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?>
<?php /**PATH /home/hatch/workspace/lentera/resources/views/reporting/sales.blade.php ENDPATH**/ ?>