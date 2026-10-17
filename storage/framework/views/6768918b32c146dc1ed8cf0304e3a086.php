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
        <h1 class="text-xl font-semibold leading-tight text-lentera-950"><?php echo e(__('ui.reports.payables')); ?></h1>
     <?php $__env->endSlot(); ?>

    <div class="py-8">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <?php echo $__env->make('reporting.partials.tabs', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            <?php echo $__env->make('reporting.partials.filters', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

            <?php $__empty_1 = true; $__currentLoopData = $suppliers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $supplier): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <div class="mb-6 overflow-hidden rounded-lg bg-white shadow-soft">
                    <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3">
                        <h2 class="text-sm font-semibold text-gray-800"><?php echo e($supplier['supplier_name']); ?></h2>
                        <div class="text-sm">
                            <span class="text-gray-500"><?php echo e(__('ui.reports.remaining')); ?>:</span>
                            <span class="font-semibold tnum"><?php echo e($supplier['total']->format()); ?></span>
                            <?php if($supplier['credit']->isPositive()): ?>
                                <span class="ms-4 text-gray-500"><?php echo e(__('ui.reports.supplier_credit')); ?>:</span>
                                <span class="font-semibold tnum"><?php echo e($supplier['credit']->format()); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if(count($supplier['receipts']) > 0): ?>
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-left text-gray-500">
                                    <th class="px-4 py-2 font-medium"><?php echo e(__('ui.reports.receipt_number')); ?></th>
                                    <th class="px-4 py-2 font-medium">PO</th>
                                    <th class="px-4 py-2 font-medium"><?php echo e(__('ui.reports.date')); ?></th>
                                    <th class="px-4 py-2 text-right font-medium"><?php echo e(__('ui.reports.total')); ?></th>
                                    <th class="px-4 py-2 text-right font-medium"><?php echo e(__('ui.reports.allocated')); ?></th>
                                    <th class="px-4 py-2 text-right font-medium"><?php echo e(__('ui.reports.remaining')); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $__currentLoopData = $supplier['receipts']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $receipt): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <tr class="border-t border-gray-50">
                                        <td class="px-4 py-2 font-medium"><?php echo e($receipt['number']); ?></td>
                                        <td class="px-4 py-2"><?php echo e($receipt['po_number']); ?></td>
                                        <td class="px-4 py-2"><?php echo e(\Illuminate\Support\Carbon::parse($receipt['date'])->format('d/m/Y')); ?></td>
                                        <td class="px-4 py-2 text-right tnum"><?php echo e($receipt['total']->format()); ?></td>
                                        <td class="px-4 py-2 text-right tnum"><?php echo e($receipt['allocated']->format()); ?></td>
                                        <td class="px-4 py-2 text-right font-medium tnum"><?php echo e($receipt['remaining']->format()); ?></td>
                                    </tr>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <div class="rounded-lg bg-white shadow-soft"><?php if (isset($component)) { $__componentOriginal1fb10244e18bb2e5d2f72f1e8eb61469 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1fb10244e18bb2e5d2f72f1e8eb61469 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.lentera.empty-state','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('lentera.empty-state'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?><?php echo e(__('ui.purchasing.no_outstanding')); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1fb10244e18bb2e5d2f72f1e8eb61469)): ?>
<?php $attributes = $__attributesOriginal1fb10244e18bb2e5d2f72f1e8eb61469; ?>
<?php unset($__attributesOriginal1fb10244e18bb2e5d2f72f1e8eb61469); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1fb10244e18bb2e5d2f72f1e8eb61469)): ?>
<?php $component = $__componentOriginal1fb10244e18bb2e5d2f72f1e8eb61469; ?>
<?php unset($__componentOriginal1fb10244e18bb2e5d2f72f1e8eb61469); ?>
<?php endif; ?></div>
            <?php endif; ?>

            <div class="rounded-lg bg-lentera-50 px-4 py-3 text-right text-sm">
                <span class="text-gray-600"><?php echo e(__('ui.reports.grand_total')); ?>:</span>
                <span class="font-semibold tnum"><?php echo e($grandTotal->format()); ?></span>
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
<?php /**PATH /home/hatch/workspace/lentera/resources/views/reporting/payables.blade.php ENDPATH**/ ?>