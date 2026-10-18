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
        <h1 class="text-xl font-semibold leading-tight text-lentera-950"><?php echo e(__('ui.offline.exceptions_title')); ?></h1>
     <?php $__env->endSlot(); ?>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
            <?php if(session('status')): ?>
                <div class="rounded-lg border border-lentera-200 bg-lentera-50 px-4 py-3 text-sm text-lentera-800"><?php echo e(session('status')); ?></div>
            <?php endif; ?>

            <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.offline.col_device')); ?></th>
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.offline.col_local')); ?></th>
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.offline.col_reason')); ?></th>
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.offline.col_items')); ?></th>
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.offline.col_status')); ?></th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__empty_1 = true; $__currentLoopData = $exceptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $exception): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <tr class="border-b border-gray-50 align-top last:border-0">
                                <td class="px-4 py-3">
                                    <span class="font-medium text-gray-800"><?php echo e($exception->device->name); ?></span>
                                    <span class="block text-xs text-gray-400"><?php echo e($exception->branch->name); ?> · <?php echo e($exception->created_at->format('d/m/Y H:i')); ?></span>
                                </td>
                                <td class="px-4 py-3 tnum"><?php echo e($exception->local_no ?? '—'); ?></td>
                                <td class="px-4 py-3"><?php echo e(__('ui.offline.reasons.'.$exception->reason)); ?></td>
                                <td class="px-4 py-3">
                                    <?php $__currentLoopData = ($exception->payload['items'] ?? []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <span class="block"><?php echo e($item['qty'] ?? '?'); ?> × <?php echo e(__('ui.offline.product_no', ['id' => $item['product_id'] ?? '?'])); ?></span>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </td>
                                <td class="px-4 py-3">
                                    <?php if($exception->status === 'pending'): ?>
                                        <span class="rounded-full bg-gold/10 px-2.5 py-0.5 text-xs font-medium text-gold-dark ring-1 ring-inset ring-gold/50"><?php echo e(__('ui.offline.status_pending')); ?></span>
                                    <?php elseif($exception->status === 'resolved'): ?>
                                        <span class="rounded-full bg-lentera-50 px-2.5 py-0.5 text-xs font-medium text-lentera-700 ring-1 ring-inset ring-lentera-600/20"><?php echo e(__('ui.offline.status_resolved')); ?></span>
                                    <?php else: ?>
                                        <span class="rounded-full bg-gray-50 px-2.5 py-0.5 text-xs font-medium text-gray-500 ring-1 ring-inset ring-gray-500/10"><?php echo e(__('ui.offline.status_discarded')); ?></span>
                                    <?php endif; ?>
                                    <?php if($exception->resolution_note): ?>
                                        <span class="mt-1 block text-xs text-gray-400"><?php echo e($exception->resolution_note); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <?php if($exception->status === 'pending'): ?>
                                        <?php if (isset($component)) { $__componentOriginal645c48eba03eed14da3653ca82ef58a2 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal645c48eba03eed14da3653ca82ef58a2 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.lentera.row-actions','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('lentera.row-actions'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
                                            <form method="POST" action="<?php echo e(route('offline-exceptions.retry', $exception)); ?>">
                                                <?php echo csrf_field(); ?>
                                                <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-gray-700 transition duration-150 hover:bg-gray-100"><?php echo e(__('ui.offline.retry')); ?></button>
                                            </form>
                                            <form method="POST" action="<?php echo e(route('offline-exceptions.discard', $exception)); ?>" onsubmit="return confirm('<?php echo e(__('ui.offline.discard_confirm')); ?>')" class="border-t border-gray-100">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="note" value="<?php echo e(__('ui.offline.discard_default_note')); ?>">
                                                <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-red-600 transition duration-150 hover:bg-red-50"><?php echo e(__('ui.offline.discard')); ?></button>
                                            </form>
                                         <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal645c48eba03eed14da3653ca82ef58a2)): ?>
<?php $attributes = $__attributesOriginal645c48eba03eed14da3653ca82ef58a2; ?>
<?php unset($__attributesOriginal645c48eba03eed14da3653ca82ef58a2); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal645c48eba03eed14da3653ca82ef58a2)): ?>
<?php $component = $__componentOriginal645c48eba03eed14da3653ca82ef58a2; ?>
<?php unset($__componentOriginal645c48eba03eed14da3653ca82ef58a2); ?>
<?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <tr><td colspan="6"><?php if (isset($component)) { $__componentOriginal1fb10244e18bb2e5d2f72f1e8eb61469 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1fb10244e18bb2e5d2f72f1e8eb61469 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.lentera.empty-state','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('lentera.empty-state'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?><?php echo e(__('ui.offline.exceptions_empty')); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1fb10244e18bb2e5d2f72f1e8eb61469)): ?>
<?php $attributes = $__attributesOriginal1fb10244e18bb2e5d2f72f1e8eb61469; ?>
<?php unset($__attributesOriginal1fb10244e18bb2e5d2f72f1e8eb61469); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1fb10244e18bb2e5d2f72f1e8eb61469)): ?>
<?php $component = $__componentOriginal1fb10244e18bb2e5d2f72f1e8eb61469; ?>
<?php unset($__componentOriginal1fb10244e18bb2e5d2f72f1e8eb61469); ?>
<?php endif; ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php echo e($exceptions->links()); ?>

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
<?php /**PATH /home/hatch/workspace/lentera/resources/views/sales/offline-exceptions/index.blade.php ENDPATH**/ ?>