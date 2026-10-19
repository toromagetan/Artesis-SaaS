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
        <h1 class="text-xl font-semibold leading-tight text-lentera-950"><?php echo e(__('ui.opname.title')); ?> — <?php echo e($opname->branch->name); ?> · <?php echo e($opname->scheduled_date->format('d/m/Y')); ?></h1>
     <?php $__env->endSlot(); ?>

    <div class="py-8">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <?php if(session('status')): ?>
                <div class="mb-4 rounded-lg border border-lentera-200 bg-lentera-50 px-4 py-3 text-sm text-lentera-800"><?php echo e(session('status')); ?></div>
            <?php endif; ?>
            <?php if($errors->any()): ?>
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?php echo e($errors->first()); ?></div>
            <?php endif; ?>

            <div class="mb-4 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-semibold <?php echo e($opname->status === 'approved' ? 'border-lentera-200 bg-lentera-50 text-lentera-700' : 'border-gray-200 bg-gray-100 text-gray-500'); ?>"><?php echo e(__('ui.opname.statuses.'.$opname->status)); ?></span>
                    <?php if($opname->isLocking()): ?>
                        <span class="rounded-md border border-gold/50 bg-gold/10 px-2 py-1 text-xs font-medium text-gold-dark"><?php echo e(__('ui.opname.lock_notice')); ?></span>
                    <?php endif; ?>
                </div>
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
                    <?php if($opname->status === 'scheduled'): ?>
                        <form method="POST" action="<?php echo e(route('opnames.start', $opname)); ?>">
                            <?php echo csrf_field(); ?>
                            <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-gray-700 transition duration-150 hover:bg-lentera-50"><?php echo e(__('ui.opname.start')); ?></button>
                        </form>
                    <?php endif; ?>
                    <?php if($opname->status === 'counting'): ?>
                        <form method="POST" action="<?php echo e(route('opnames.review', $opname)); ?>">
                            <?php echo csrf_field(); ?>
                            <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-gray-700 transition duration-150 hover:bg-lentera-50"><?php echo e(__('ui.opname.submit_review')); ?></button>
                        </form>
                    <?php endif; ?>
                    <?php if($opname->status === 'review'): ?>
                        <form method="POST" action="<?php echo e(route('opnames.approve', $opname)); ?>">
                            <?php echo csrf_field(); ?>
                            <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-gray-700 transition duration-150 hover:bg-lentera-50"><?php echo e(__('ui.opname.approve')); ?></button>
                        </form>
                    <?php endif; ?>
                    <?php if($opname->status === 'counting' && auth()->user()->hasRole('Owner')): ?>
                        <div class="my-1 border-t border-gray-100"></div>
                        <form method="POST" action="<?php echo e(route('opnames.cancel', $opname)); ?>" onsubmit="return confirm('<?php echo e(__('ui.opname.cancel_confirm')); ?>')">
                            <?php echo csrf_field(); ?>
                            <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-red-600 transition duration-150 hover:bg-lentera-50"><?php echo e(__('ui.opname.cancel')); ?></button>
                        </form>
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

            <div class="mb-4 grid gap-4 text-sm sm:grid-cols-4">
                <div class="rounded-lg bg-white p-4 shadow-soft"><p class="text-gray-500"><?php echo e(__('ui.opname.branch')); ?></p><p class="font-medium"><?php echo e($opname->branch->name); ?></p></div>
                <div class="rounded-lg bg-white p-4 shadow-soft"><p class="text-gray-500"><?php echo e(__('ui.opname.created_by')); ?></p><p class="font-medium"><?php echo e($opname->creator->name); ?></p></div>
                <div class="rounded-lg bg-white p-4 shadow-soft"><p class="text-gray-500"><?php echo e(__('ui.opname.approved_by')); ?></p><p class="font-medium"><?php echo e($opname->approver?->name ?? '—'); ?></p></div>
                <div class="rounded-lg bg-white p-4 shadow-soft"><p class="text-gray-500"><?php echo e(__('ui.opname.notes')); ?></p><p class="font-medium"><?php echo e($opname->notes ?? '—'); ?></p></div>
            </div>

            <?php if($opname->status === 'counting'): ?>
                <form method="POST" action="<?php echo e(route('opnames.count', $opname)); ?>">
                    <?php echo csrf_field(); ?>
            <?php endif; ?>
            <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.opname.product')); ?></th>
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.opname.sku')); ?></th>
                            <th class="px-4 py-3 text-right font-medium"><?php echo e(__('ui.opname.system_qty')); ?></th>
                            <th class="px-4 py-3 text-right font-medium"><?php echo e(__('ui.opname.counted_qty')); ?></th>
                            <th class="px-4 py-3 text-right font-medium"><?php echo e(__('ui.opname.difference')); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__empty_1 = true; $__currentLoopData = $flatItems; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3"><?php echo e($item->product->name); ?></td>
                                <td class="px-4 py-3"><?php echo e($item->product->sku); ?></td>
                                <td class="px-4 py-3 text-right tabular-nums"><?php echo e($item->system_qty); ?></td>
                                <td class="px-4 py-3 text-right">
                                    <?php if($opname->status === 'counting'): ?>
                                        <input type="number" min="0" name="counts[<?php echo e($item->id); ?>]" value="<?php echo e($item->counted_qty); ?>" class="w-24 rounded-md border-gray-300 text-right text-sm shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                                    <?php else: ?>
                                        <span class="tabular-nums"><?php echo e($item->counted_qty ?? '—'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums <?php echo e($item->difference !== 0 ? 'font-semibold text-red-600' : ''); ?>"><?php echo e($item->counted_qty === null ? '—' : ($item->difference > 0 ? '+'.$item->difference : $item->difference)); ?></td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <?php if(empty($matrixGroups)): ?>
                                <tr><td colspan="5"><?php if (isset($component)) { $__componentOriginal1fb10244e18bb2e5d2f72f1e8eb61469 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1fb10244e18bb2e5d2f72f1e8eb61469 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.lentera.empty-state','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('lentera.empty-state'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?><?php echo e(__('ui.opname.no_items')); ?> <?php echo $__env->renderComponent(); ?>
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
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php $__currentLoopData = $matrixGroups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $group): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div class="mt-4 overflow-hidden rounded-lg bg-white p-4 shadow-soft">
                    <h2 class="text-sm font-semibold text-gray-700"><?php echo e(__('ui.matrix.title')); ?> — <?php echo e($group['parent']->brand ? $group['parent']->brand.' ' : ''); ?><?php echo e($group['parent']->name); ?></h2>
                    <p class="mb-2 mt-0.5 text-xs text-gray-500"><?php echo e(__('ui.matrix.opname_help')); ?></p>
                    <div class="overflow-x-auto">
                        <table class="min-w-full border-collapse text-sm">
                            <thead>
                                <tr class="text-left text-gray-500">
                                    <th class="border border-gray-100 px-2 py-1.5 font-medium">SPH \ <?php echo e($group['col_label']); ?></th>
                                    <?php $__currentLoopData = $group['cols']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $col): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <th class="border border-gray-100 px-2 py-1.5 text-right font-medium tnum"><?php echo e($matrixService->display($col)); ?></th>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $__currentLoopData = $group['rows']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <tr>
                                        <th class="border border-gray-100 px-2 py-1.5 text-left font-medium text-gray-600 tnum"><?php echo e($row['label']); ?></th>
                                        <?php $__currentLoopData = $group['cols']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $col): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <td class="border border-gray-100 px-2 py-1.5 align-top">
                                                <?php $__currentLoopData = $row['cells'][$matrixService->colKey($col)] ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                    <div class="mb-1 last:mb-0">
                                                        <div class="flex items-center justify-between gap-2 text-xs text-gray-500">
                                                            <span><?php echo e($item->product->sku); ?><?php if($item->product->eye_side): ?> · <?php echo e($item->product->eye_side); ?><?php endif; ?></span>
                                                            <span><?php echo e(__('ui.opname.system_qty')); ?>: <span class="tnum"><?php echo e($item->system_qty); ?></span></span>
                                                        </div>
                                                        <?php if($opname->status === 'counting'): ?>
                                                            <input type="number" min="0" name="counts[<?php echo e($item->id); ?>]" value="<?php echo e($item->counted_qty); ?>" class="mt-0.5 block w-20 rounded-md border-gray-300 text-right text-sm shadow-sm focus:border-lentera-500 focus:ring-lentera-500" placeholder="0">
                                                        <?php else: ?>
                                                            <div class="text-right text-sm tabular-nums"><?php echo e($item->counted_qty ?? '—'); ?></div>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                            </td>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    </tr>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            <?php if($opname->status === 'counting'): ?>
                    <div class="mt-4 flex items-center gap-3">
                        <?php if (isset($component)) { $__componentOriginald411d1792bd6cc877d687758b753742c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald411d1792bd6cc877d687758b753742c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.primary-button','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('primary-button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?><?php echo e(__('ui.opname.save_counts')); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald411d1792bd6cc877d687758b753742c)): ?>
<?php $attributes = $__attributesOriginald411d1792bd6cc877d687758b753742c; ?>
<?php unset($__attributesOriginald411d1792bd6cc877d687758b753742c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald411d1792bd6cc877d687758b753742c)): ?>
<?php $component = $__componentOriginald411d1792bd6cc877d687758b753742c; ?>
<?php unset($__componentOriginald411d1792bd6cc877d687758b753742c); ?>
<?php endif; ?>
                        <a href="<?php echo e(route('opnames.index')); ?>" class="text-sm text-gray-500 hover:text-gray-700"><?php echo e(__('ui.back')); ?></a>
                    </div>
                </form>
            <?php else: ?>
                <div class="mt-4"><a href="<?php echo e(route('opnames.index')); ?>" class="text-sm text-gray-500 hover:text-gray-700"><?php echo e(__('ui.back')); ?></a></div>
            <?php endif; ?>
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
<?php /**PATH /home/hatch/workspace/lentera/resources/views/inventory/opname/show.blade.php ENDPATH**/ ?>