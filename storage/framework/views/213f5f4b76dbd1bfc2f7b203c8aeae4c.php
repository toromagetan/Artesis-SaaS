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
        <h1 class="text-xl font-semibold leading-tight text-lentera-950"><?php echo e(__('ui.financial.income_statement')); ?></h1>
     <?php $__env->endSlot(); ?>

    <div class="py-8">
        <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
            <?php echo $__env->make('reporting.partials.tabs', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            <?php echo $__env->make('accounting.financial.tabs', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            <?php echo $__env->make('reporting.partials.filters', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

            <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <tbody>
                        <tr class="border-b border-gray-100 bg-gray-50">
                            <td colspan="2" class="px-4 py-3 font-semibold text-gray-800"><?php echo e(__('ui.financial.revenue')); ?></td>
                        </tr>
                        <?php $__empty_1 = true; $__currentLoopData = $statement['revenues']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <tr class="border-b border-gray-50">
                                <td class="px-4 py-2.5"><span class="text-gray-500 tnum"><?php echo e($row['account']->code); ?></span> <?php echo e($row['account']->name); ?></td>
                                <td class="px-4 py-2.5 text-right tnum"><?php echo e($row['balance']->format()); ?></td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <tr class="border-b border-gray-50"><td colspan="2" class="px-4 py-3 text-gray-500">—</td></tr>
                        <?php endif; ?>
                        <tr class="border-b border-gray-100 font-medium">
                            <td class="px-4 py-3"><?php echo e(__('ui.financial.total_revenue')); ?></td>
                            <td class="px-4 py-3 text-right tnum"><?php echo e($statement['total_revenue']->format()); ?></td>
                        </tr>
                        <tr class="border-b border-gray-100 bg-gray-50">
                            <td colspan="2" class="px-4 py-3 font-semibold text-gray-800"><?php echo e(__('ui.financial.expenses')); ?></td>
                        </tr>
                        <?php $__empty_1 = true; $__currentLoopData = $statement['expenses']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <tr class="border-b border-gray-50">
                                <td class="px-4 py-2.5"><span class="text-gray-500 tnum"><?php echo e($row['account']->code); ?></span> <?php echo e($row['account']->name); ?></td>
                                <td class="px-4 py-2.5 text-right tnum"><?php echo e($row['balance']->format()); ?></td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <tr class="border-b border-gray-50"><td colspan="2" class="px-4 py-3 text-gray-500">—</td></tr>
                        <?php endif; ?>
                        <tr class="border-b border-gray-100 font-medium">
                            <td class="px-4 py-3"><?php echo e(__('ui.financial.total_expenses')); ?></td>
                            <td class="px-4 py-3 text-right tnum"><?php echo e($statement['total_expense']->format()); ?></td>
                        </tr>
                        <tr class="bg-lentera-50 font-semibold text-lentera-900">
                            <td class="px-4 py-3"><?php echo e(__('ui.financial.net_income')); ?></td>
                            <td class="px-4 py-3 text-right tnum"><?php echo e($statement['net']->format()); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-6 overflow-hidden rounded-lg bg-white shadow-soft">
                <div class="border-b border-gray-100 px-4 py-3 font-semibold text-gray-800"><?php echo e(__('ui.financial.per_branch')); ?></div>
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.reports.branch')); ?></th>
                            <th class="px-4 py-3 text-right font-medium"><?php echo e(__('ui.financial.revenue')); ?></th>
                            <th class="px-4 py-3 text-right font-medium"><?php echo e(__('ui.financial.expenses')); ?></th>
                            <th class="px-4 py-3 text-right font-medium"><?php echo e(__('ui.financial.net_income')); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__empty_1 = true; $__currentLoopData = $byBranch; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 font-medium text-gray-800"><?php echo e($row['branch_id'] === null ? __('ui.financial.no_branch') : ($branches->firstWhere('id', $row['branch_id'])?->name ?? '#'.$row['branch_id'])); ?></td>
                                <td class="px-4 py-3 text-right tnum"><?php echo e($row['revenue']->format()); ?></td>
                                <td class="px-4 py-3 text-right tnum"><?php echo e($row['expense']->format()); ?></td>
                                <td class="px-4 py-3 text-right font-medium tnum"><?php echo e($row['net']->format()); ?></td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <tr><td colspan="4" class="px-4 py-3 text-gray-500">—</td></tr>
                        <?php endif; ?>
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
<?php /**PATH /home/hatch/workspace/lentera/resources/views/accounting/financial/income.blade.php ENDPATH**/ ?>