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
        <h1 class="text-xl font-semibold leading-tight text-lentera-950"><?php echo e(__('ui.import.preview_title')); ?></h1>
     <?php $__env->endSlot(); ?>

    <div class="py-8">
        <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
            <?php if($errors->any()): ?>
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><div><?php echo e($error); ?></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            <?php endif; ?>

            <div class="mb-6 grid gap-4 md:grid-cols-2">
                <div class="rounded-lg bg-white p-6 shadow-soft">
                    <p class="text-sm text-gray-500"><?php echo e(__('ui.import.valid_rows')); ?></p>
                    <p class="text-2xl font-semibold tnum"><?php echo e(count($preview['valid'])); ?></p>
                </div>
                <div class="rounded-lg bg-white p-6 shadow-soft">
                    <p class="text-sm text-gray-500"><?php echo e(__('ui.import.error_rows')); ?></p>
                    <p class="text-2xl font-semibold tnum <?php echo e(count($preview['errors']) > 0 ? 'text-red-600' : ''); ?>"><?php echo e(count($preview['errors'])); ?></p>
                </div>
            </div>

            <?php if(count($preview['errors']) > 0): ?>
                <div class="mb-6 overflow-hidden rounded-lg bg-white shadow-soft">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 text-left text-gray-500">
                                <th class="px-4 py-3 font-medium"><?php echo e(__('ui.import.line')); ?></th>
                                <th class="px-4 py-3 font-medium"><?php echo e(__('ui.stock.reason')); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $__currentLoopData = $preview['errors']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <tr class="border-b border-gray-50 last:border-0">
                                    <td class="px-4 py-3 tnum"><?php echo e($error['line']); ?></td>
                                    <td class="px-4 py-3 text-red-600"><?php echo e($error['message']); ?></td>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <div class="mb-6 overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.import.line')); ?></th>
                            <th class="px-4 py-3 font-medium">SKU</th>
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.stock.branch')); ?></th>
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.stock.qty')); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__currentLoopData = $preview['valid']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 tnum"><?php echo e($row['line']); ?></td>
                                <td class="px-4 py-3 font-medium text-gray-800"><?php echo e($row['sku']); ?></td>
                                <td class="px-4 py-3"><?php echo e($row['branch_name']); ?></td>
                                <td class="px-4 py-3 tnum"><?php echo e($row['qty']); ?></td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </tbody>
                </table>
            </div>

            <div class="flex items-center gap-3">
                <a href="<?php echo e(route('stock.import.form')); ?>" class="text-sm text-gray-600 underline"><?php echo e(__('ui.back')); ?></a>
                <?php if(count($preview['errors']) === 0 && count($preview['valid']) > 0): ?>
                    <form method="POST" action="<?php echo e(route('stock.import.commit')); ?>" onsubmit="return confirm('<?php echo e(__('ui.import.commit')); ?>?')">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="token" value="<?php echo e($preview['token']); ?>">
                        <?php if (isset($component)) { $__componentOriginald411d1792bd6cc877d687758b753742c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald411d1792bd6cc877d687758b753742c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.primary-button','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('primary-button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?><?php echo e(__('ui.import.commit')); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald411d1792bd6cc877d687758b753742c)): ?>
<?php $attributes = $__attributesOriginald411d1792bd6cc877d687758b753742c; ?>
<?php unset($__attributesOriginald411d1792bd6cc877d687758b753742c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald411d1792bd6cc877d687758b753742c)): ?>
<?php $component = $__componentOriginald411d1792bd6cc877d687758b753742c; ?>
<?php unset($__componentOriginald411d1792bd6cc877d687758b753742c); ?>
<?php endif; ?>
                    </form>
                <?php endif; ?>
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
<?php /**PATH /home/hatch/workspace/lentera/resources/views/inventory/import/preview.blade.php ENDPATH**/ ?>