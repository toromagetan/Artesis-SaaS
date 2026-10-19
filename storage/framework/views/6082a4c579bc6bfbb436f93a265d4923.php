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
        <h1 class="text-xl font-semibold leading-tight text-lentera-950"><?php echo e(__('ui.audit_logs.detail')); ?></h1>
     <?php $__env->endSlot(); ?>

    <div class="py-8">
        <div class="mx-auto max-w-5xl space-y-4 sm:px-6 lg:px-8">
            <p><a href="<?php echo e(route('audit-logs.index')); ?>" class="text-sm text-lentera-800 underline"><?php echo e(__('ui.audit_logs.back')); ?></a></p>

            <section class="rounded-lg bg-white p-6 shadow-soft">
                <dl class="grid gap-3 text-sm sm:grid-cols-2">
                    <div><dt class="text-gray-500"><?php echo e(__('ui.audit_logs.time')); ?></dt><dd class="font-medium tnum"><?php echo e($log->created_at->format('d/m/Y H:i:s')); ?></dd></div>
                    <div><dt class="text-gray-500"><?php echo e(__('ui.audit_logs.actor')); ?></dt><dd class="font-medium"><?php echo e($log->user?->name ?? '—'); ?></dd></div>
                    <div><dt class="text-gray-500"><?php echo e(__('ui.audit_logs.action')); ?></dt><dd class="font-medium"><?php echo e($log->action); ?></dd></div>
                    <div><dt class="text-gray-500"><?php echo e(__('ui.audit_logs.subject')); ?></dt><dd class="font-medium"><?php echo e(class_basename($log->subject_type)); ?> #<?php echo e($log->subject_id); ?></dd></div>
                    <div><dt class="text-gray-500"><?php echo e(__('ui.audit_logs.branch')); ?></dt><dd class="font-medium"><?php echo e($log->branch?->name ?? '—'); ?></dd></div>
                    <div><dt class="text-gray-500">IP</dt><dd class="font-medium tnum"><?php echo e($log->ip ?? '—'); ?></dd></div>
                </dl>
            </section>

            <div class="grid gap-4 lg:grid-cols-2">
                <section class="overflow-hidden rounded-lg bg-white shadow-soft">
                    <h2 class="border-b border-gray-100 px-4 py-3 font-semibold text-gray-800"><?php echo e(__('ui.audit_logs.old_values')); ?></h2>
                    <?php if(! empty($log->old_values)): ?>
                        <table class="min-w-full text-sm">
                            <tbody>
                                <?php $__currentLoopData = $log->old_values; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <tr class="border-b border-gray-50 last:border-0">
                                        <td class="px-4 py-2 font-medium text-gray-600"><?php echo e($key); ?></td>
                                        <td class="px-4 py-2 text-right"><?php echo e(is_scalar($value) || $value === null ? (string) $value : json_encode($value)); ?></td>
                                    </tr>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <?php if (isset($component)) { $__componentOriginal1fb10244e18bb2e5d2f72f1e8eb61469 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1fb10244e18bb2e5d2f72f1e8eb61469 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.lentera.empty-state','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('lentera.empty-state'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?><?php echo e(__('ui.audit_logs.none')); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1fb10244e18bb2e5d2f72f1e8eb61469)): ?>
<?php $attributes = $__attributesOriginal1fb10244e18bb2e5d2f72f1e8eb61469; ?>
<?php unset($__attributesOriginal1fb10244e18bb2e5d2f72f1e8eb61469); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1fb10244e18bb2e5d2f72f1e8eb61469)): ?>
<?php $component = $__componentOriginal1fb10244e18bb2e5d2f72f1e8eb61469; ?>
<?php unset($__componentOriginal1fb10244e18bb2e5d2f72f1e8eb61469); ?>
<?php endif; ?>
                    <?php endif; ?>
                </section>
                <section class="overflow-hidden rounded-lg bg-white shadow-soft">
                    <h2 class="border-b border-gray-100 px-4 py-3 font-semibold text-gray-800"><?php echo e(__('ui.audit_logs.new_values')); ?></h2>
                    <?php if(! empty($log->new_values)): ?>
                        <table class="min-w-full text-sm">
                            <tbody>
                                <?php $__currentLoopData = $log->new_values; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <tr class="border-b border-gray-50 last:border-0">
                                        <td class="px-4 py-2 font-medium text-gray-600"><?php echo e($key); ?></td>
                                        <td class="px-4 py-2 text-right"><?php echo e(is_scalar($value) || $value === null ? (string) $value : json_encode($value)); ?></td>
                                    </tr>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <?php if (isset($component)) { $__componentOriginal1fb10244e18bb2e5d2f72f1e8eb61469 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1fb10244e18bb2e5d2f72f1e8eb61469 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.lentera.empty-state','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('lentera.empty-state'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?><?php echo e(__('ui.audit_logs.none')); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1fb10244e18bb2e5d2f72f1e8eb61469)): ?>
<?php $attributes = $__attributesOriginal1fb10244e18bb2e5d2f72f1e8eb61469; ?>
<?php unset($__attributesOriginal1fb10244e18bb2e5d2f72f1e8eb61469); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1fb10244e18bb2e5d2f72f1e8eb61469)): ?>
<?php $component = $__componentOriginal1fb10244e18bb2e5d2f72f1e8eb61469; ?>
<?php unset($__componentOriginal1fb10244e18bb2e5d2f72f1e8eb61469); ?>
<?php endif; ?>
                    <?php endif; ?>
                </section>
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
<?php /**PATH /home/hatch/workspace/lentera/resources/views/settings/audit-logs/show.blade.php ENDPATH**/ ?>