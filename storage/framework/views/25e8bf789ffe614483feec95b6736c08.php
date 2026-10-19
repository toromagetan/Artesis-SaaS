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
        <h1 class="text-xl font-semibold leading-tight text-lentera-950"><?php echo e(__('ui.audit_logs.title')); ?></h1>
     <?php $__env->endSlot(); ?>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-4 sm:px-6 lg:px-8">
            <form method="GET" class="flex flex-wrap items-end gap-3 rounded-lg bg-white p-4 shadow-soft">
                <label class="text-sm">
                    <span class="mb-1 block text-gray-500"><?php echo e(__('ui.audit_logs.action')); ?></span>
                    <input type="text" name="aksi" value="<?php echo e($filters['aksi'] ?? ''); ?>" class="rounded-md border-gray-300 text-sm" placeholder="sale.create">
                </label>
                <label class="text-sm">
                    <span class="mb-1 block text-gray-500"><?php echo e(__('ui.audit_logs.subject')); ?></span>
                    <select name="subjek" class="rounded-md border-gray-300 text-sm">
                        <option value=""><?php echo e(__('ui.audit_logs.all')); ?></option>
                        <?php $__currentLoopData = $subjectTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($type); ?>" <?php if(($filters['subjek'] ?? '') === $type): echo 'selected'; endif; ?>><?php echo e(class_basename($type)); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </label>
                <label class="text-sm">
                    <span class="mb-1 block text-gray-500"><?php echo e(__('ui.audit_logs.actor')); ?></span>
                    <select name="pengguna" class="rounded-md border-gray-300 text-sm">
                        <option value=""><?php echo e(__('ui.audit_logs.all')); ?></option>
                        <?php $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $u): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($u->id); ?>" <?php if((int) ($filters['pengguna'] ?? 0) === $u->id): echo 'selected'; endif; ?>><?php echo e($u->name); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </label>
                <label class="text-sm">
                    <span class="mb-1 block text-gray-500"><?php echo e(__('ui.audit_logs.branch')); ?></span>
                    <select name="cabang" class="rounded-md border-gray-300 text-sm">
                        <option value=""><?php echo e(__('ui.audit_logs.all')); ?></option>
                        <?php $__currentLoopData = $branches; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $b): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($b->id); ?>" <?php if((int) ($filters['cabang'] ?? 0) === $b->id): echo 'selected'; endif; ?>><?php echo e($b->name); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </label>
                <label class="text-sm">
                    <span class="mb-1 block text-gray-500"><?php echo e(__('ui.audit_logs.from')); ?></span>
                    <input type="date" name="dari" value="<?php echo e($filters['dari'] ?? ''); ?>" class="rounded-md border-gray-300 text-sm">
                </label>
                <label class="text-sm">
                    <span class="mb-1 block text-gray-500"><?php echo e(__('ui.audit_logs.to')); ?></span>
                    <input type="date" name="sampai" value="<?php echo e($filters['sampai'] ?? ''); ?>" class="rounded-md border-gray-300 text-sm">
                </label>
                <button type="submit" class="rounded-md bg-lentera-700 px-4 py-2 text-sm font-medium text-white transition duration-150 hover:bg-lentera-800"><?php echo e(__('ui.audit_logs.apply')); ?></button>
            </form>

            <section class="overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.audit_logs.time')); ?></th>
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.audit_logs.actor')); ?></th>
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.audit_logs.action')); ?></th>
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.audit_logs.subject')); ?></th>
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.audit_logs.branch')); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__empty_1 = true; $__currentLoopData = $logs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $log): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <tr class="border-b border-gray-50 last:border-0 hover:bg-gray-50">
                                <td class="px-4 py-3 tnum"><?php echo e($log->created_at->format('d/m/Y H:i:s')); ?></td>
                                <td class="px-4 py-3"><?php echo e($log->user?->name ?? '—'); ?></td>
                                <td class="px-4 py-3">
                                    <a href="<?php echo e(route('audit-logs.show', $log)); ?>" class="font-medium text-lentera-800 underline"><?php echo e($log->action); ?></a>
                                </td>
                                <td class="px-4 py-3"><?php echo e(class_basename($log->subject_type)); ?> #<?php echo e($log->subject_id); ?></td>
                                <td class="px-4 py-3"><?php echo e($log->branch?->name ?? '—'); ?></td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <tr><td colspan="5"><?php if (isset($component)) { $__componentOriginal1fb10244e18bb2e5d2f72f1e8eb61469 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1fb10244e18bb2e5d2f72f1e8eb61469 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.lentera.empty-state','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('lentera.empty-state'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?><?php echo e(__('ui.audit_logs.empty')); ?> <?php echo $__env->renderComponent(); ?>
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
                <div class="border-t border-gray-50 px-4 py-3"><?php echo e($logs->links()); ?></div>
            </section>
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
<?php /**PATH /home/hatch/workspace/lentera/resources/views/settings/audit-logs/index.blade.php ENDPATH**/ ?>