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
        <h1 class="text-xl font-semibold leading-tight text-lentera-950"><?php echo e(__('ui.purchasing.pr_title')); ?></h1>
     <?php $__env->endSlot(); ?>

    <div class="py-8">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <?php if(session('status')): ?>
                <div class="mb-4 rounded-lg border border-lentera-200 bg-lentera-50 px-4 py-3 text-sm text-lentera-800"><?php echo e(session('status')); ?></div>
            <?php endif; ?>
            <?php if($errors->any()): ?>
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><div><?php echo e($error); ?></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            <?php endif; ?>

            <div class="mb-4 flex items-center justify-between">
                <form method="GET" class="flex gap-2">
                    <?php if (isset($component)) { $__componentOriginal18c21970322f9e5c938bc954620c12bb = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal18c21970322f9e5c938bc954620c12bb = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.text-input','data' => ['name' => 'q','value' => $q,'placeholder' => __('ui.purchasing.search_placeholder'),'class' => 'w-64']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('text-input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'q','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($q),'placeholder' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.purchasing.search_placeholder')),'class' => 'w-64']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal18c21970322f9e5c938bc954620c12bb)): ?>
<?php $attributes = $__attributesOriginal18c21970322f9e5c938bc954620c12bb; ?>
<?php unset($__attributesOriginal18c21970322f9e5c938bc954620c12bb); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal18c21970322f9e5c938bc954620c12bb)): ?>
<?php $component = $__componentOriginal18c21970322f9e5c938bc954620c12bb; ?>
<?php unset($__componentOriginal18c21970322f9e5c938bc954620c12bb); ?>
<?php endif; ?>
                    <select name="status" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                        <option value=""><?php echo e(__('ui.purchasing.status')); ?></option>
                        <?php $__currentLoopData = \App\Modules\Purchasing\Models\PurchaseRequest::STATUSES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($s); ?>" <?php if($status === $s): echo 'selected'; endif; ?>><?php echo e(__('ui.purchasing.pr_statuses.'.$s)); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                    <?php if (isset($component)) { $__componentOriginal3b0e04e43cf890250cc4d85cff4d94af = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal3b0e04e43cf890250cc4d85cff4d94af = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.secondary-button','data' => ['type' => 'submit']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('secondary-button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'submit']); ?><?php echo e(__('ui.search')); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal3b0e04e43cf890250cc4d85cff4d94af)): ?>
<?php $attributes = $__attributesOriginal3b0e04e43cf890250cc4d85cff4d94af; ?>
<?php unset($__attributesOriginal3b0e04e43cf890250cc4d85cff4d94af); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal3b0e04e43cf890250cc4d85cff4d94af)): ?>
<?php $component = $__componentOriginal3b0e04e43cf890250cc4d85cff4d94af; ?>
<?php unset($__componentOriginal3b0e04e43cf890250cc4d85cff4d94af); ?>
<?php endif; ?>
                </form>
                <?php if (isset($component)) { $__componentOriginald1b6773295f170ebd8a8786911868804 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald1b6773295f170ebd8a8786911868804 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.lentera.dropdown-aksi','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('lentera.dropdown-aksi'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('pr.create')): ?>
                        <?php if (isset($component)) { $__componentOriginal462fb62fb1260a0c7276ba80b762793e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal462fb62fb1260a0c7276ba80b762793e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.lentera.menu-item','data' => ['href' => route('pr.create')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('lentera.menu-item'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('pr.create'))]); ?><?php echo e(__('ui.purchasing.pr_create')); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal462fb62fb1260a0c7276ba80b762793e)): ?>
<?php $attributes = $__attributesOriginal462fb62fb1260a0c7276ba80b762793e; ?>
<?php unset($__attributesOriginal462fb62fb1260a0c7276ba80b762793e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal462fb62fb1260a0c7276ba80b762793e)): ?>
<?php $component = $__componentOriginal462fb62fb1260a0c7276ba80b762793e; ?>
<?php unset($__componentOriginal462fb62fb1260a0c7276ba80b762793e); ?>
<?php endif; ?>
                    <?php endif; ?>
                 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald1b6773295f170ebd8a8786911868804)): ?>
<?php $attributes = $__attributesOriginald1b6773295f170ebd8a8786911868804; ?>
<?php unset($__attributesOriginald1b6773295f170ebd8a8786911868804); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald1b6773295f170ebd8a8786911868804)): ?>
<?php $component = $__componentOriginald1b6773295f170ebd8a8786911868804; ?>
<?php unset($__componentOriginald1b6773295f170ebd8a8786911868804); ?>
<?php endif; ?>
            </div>

            <form method="POST" action="<?php echo e(route('pr.convert-multiple')); ?>">
                <?php echo csrf_field(); ?>
                <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 text-left text-gray-500">
                                <th class="px-4 py-3 font-medium"></th>
                                <th class="px-4 py-3 font-medium"><?php echo e(__('ui.purchasing.number')); ?></th>
                                <th class="px-4 py-3 font-medium"><?php echo e(__('ui.purchasing.date')); ?></th>
                                <th class="px-4 py-3 font-medium"><?php echo e(__('ui.purchasing.requester')); ?></th>
                                <th class="px-4 py-3 font-medium"><?php echo e(__('ui.purchasing.branch')); ?></th>
                                <th class="px-4 py-3 font-medium"><?php echo e(__('ui.purchasing.items')); ?></th>
                                <th class="px-4 py-3 font-medium"><?php echo e(__('ui.purchasing.status')); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $__empty_1 = true; $__currentLoopData = $requests; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $pr): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                <tr class="border-b border-gray-50 last:border-0">
                                    <td class="px-4 py-3">
                                        <?php if($pr->status === 'approved'): ?>
                                            <input type="checkbox" name="pr_ids[]" value="<?php echo e($pr->id); ?>" class="rounded border-gray-300 text-lentera-700 focus:ring-lentera-500">
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 font-medium"><a href="<?php echo e(route('pr.show', $pr)); ?>" class="text-lentera-800 underline-offset-2 hover:underline"><?php echo e($pr->number); ?></a></td>
                                    <td class="px-4 py-3"><?php echo e($pr->created_at->format('d/m/Y')); ?></td>
                                    <td class="px-4 py-3"><?php echo e($pr->requester->name); ?></td>
                                    <td class="px-4 py-3"><?php echo e($pr->branch->name); ?></td>
                                    <td class="px-4 py-3 tnum"><?php echo e($pr->items_count); ?></td>
                                    <td class="px-4 py-3"><?php if (isset($component)) { $__componentOriginal1a10f463ba2873b939123688d39cb7c8 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1a10f463ba2873b939123688d39cb7c8 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.lentera.badge','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('lentera.badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?><?php echo e(__('ui.purchasing.pr_statuses.'.$pr->status)); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1a10f463ba2873b939123688d39cb7c8)): ?>
<?php $attributes = $__attributesOriginal1a10f463ba2873b939123688d39cb7c8; ?>
<?php unset($__attributesOriginal1a10f463ba2873b939123688d39cb7c8); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1a10f463ba2873b939123688d39cb7c8)): ?>
<?php $component = $__componentOriginal1a10f463ba2873b939123688d39cb7c8; ?>
<?php unset($__componentOriginal1a10f463ba2873b939123688d39cb7c8); ?>
<?php endif; ?></td>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                <tr><td colspan="7"><?php if (isset($component)) { $__componentOriginal1fb10244e18bb2e5d2f72f1e8eb61469 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1fb10244e18bb2e5d2f72f1e8eb61469 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.lentera.empty-state','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('lentera.empty-state'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?><?php echo e(__('ui.purchasing.empty_pr')); ?> <?php echo $__env->renderComponent(); ?>
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

                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('pr.approve')): ?>
                    <div class="mt-4 flex flex-wrap items-end gap-3 rounded-lg bg-white p-4 shadow-soft">
                        <div>
                            <?php if (isset($component)) { $__componentOriginale3da9d84bb64e4bc2eeebaafabfb2581 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale3da9d84bb64e4bc2eeebaafabfb2581 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.input-label','data' => ['for' => 'merge_supplier','value' => __('ui.purchasing.supplier')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('input-label'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['for' => 'merge_supplier','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.purchasing.supplier'))]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginale3da9d84bb64e4bc2eeebaafabfb2581)): ?>
<?php $attributes = $__attributesOriginale3da9d84bb64e4bc2eeebaafabfb2581; ?>
<?php unset($__attributesOriginale3da9d84bb64e4bc2eeebaafabfb2581); ?>
<?php endif; ?>
<?php if (isset($__componentOriginale3da9d84bb64e4bc2eeebaafabfb2581)): ?>
<?php $component = $__componentOriginale3da9d84bb64e4bc2eeebaafabfb2581; ?>
<?php unset($__componentOriginale3da9d84bb64e4bc2eeebaafabfb2581); ?>
<?php endif; ?>
                            <select id="merge_supplier" name="supplier_id" class="mt-1 block w-64 rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                                <?php $__currentLoopData = \App\Modules\Master\Models\Supplier::where('is_active', true)->orderBy('name')->get(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $supplier): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($supplier->id); ?>"><?php echo e($supplier->name); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                        </div>
                        <?php if (isset($component)) { $__componentOriginal3b0e04e43cf890250cc4d85cff4d94af = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal3b0e04e43cf890250cc4d85cff4d94af = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.secondary-button','data' => ['type' => 'submit']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('secondary-button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'submit']); ?><?php echo e(__('ui.purchasing.convert_multi')); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal3b0e04e43cf890250cc4d85cff4d94af)): ?>
<?php $attributes = $__attributesOriginal3b0e04e43cf890250cc4d85cff4d94af; ?>
<?php unset($__attributesOriginal3b0e04e43cf890250cc4d85cff4d94af); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal3b0e04e43cf890250cc4d85cff4d94af)): ?>
<?php $component = $__componentOriginal3b0e04e43cf890250cc4d85cff4d94af; ?>
<?php unset($__componentOriginal3b0e04e43cf890250cc4d85cff4d94af); ?>
<?php endif; ?>
                        <p class="text-xs text-gray-400"><?php echo e(__('ui.purchasing.convert_multi')); ?>: centang PR berstatus Approved (supplier & cabang tujuan sama).</p>
                    </div>
                <?php endif; ?>
            </form>

            <div class="mt-4"><?php echo e($requests->links()); ?></div>
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
<?php /**PATH /home/hatch/workspace/lentera/resources/views/purchasing/requests/index.blade.php ENDPATH**/ ?>