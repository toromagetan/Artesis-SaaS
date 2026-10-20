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
        <h1 class="text-xl font-semibold leading-tight text-lentera-950"><?php echo e(__('ui.incentives.detail')); ?> — <?php echo e($period->period_start->format('F Y')); ?></h1>
     <?php $__env->endSlot(); ?>

    <div class="py-8">
        <div class="mx-auto max-w-6xl space-y-4 sm:px-6 lg:px-8">
            <?php if(session('status')): ?>
                <p class="rounded-md bg-lentera-100 px-4 py-2 text-sm text-lentera-900" role="status"><?php echo e(session('status')); ?></p>
            <?php endif; ?>
            <?php if($errors->any()): ?>
                <ul class="rounded-md bg-red-50 px-4 py-2 text-sm text-red-700">
                    <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($error); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </ul>
            <?php endif; ?>
            <div class="flex items-center justify-between">
                <p><a href="<?php echo e(route('incentives.index')); ?>" class="text-sm text-lentera-800 underline"><?php echo e(__('ui.incentives.back')); ?></a></p>
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
                    <?php if($period->isDraft()): ?>
                        <form method="POST" action="<?php echo e(route('incentives.approve', $period)); ?>">
                            <?php echo csrf_field(); ?>
                            <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100"><?php echo e(__('ui.incentives.approve')); ?></button>
                        </form>
                    <?php endif; ?>
                    <?php if($period->status === 'approved'): ?>
                        <form method="POST" action="<?php echo e(route('incentives.pay', $period)); ?>">
                            <?php echo csrf_field(); ?>
                            <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100"><?php echo e(__('ui.incentives.pay')); ?></button>
                        </form>
                    <?php endif; ?>
                    <?php if (isset($component)) { $__componentOriginal462fb62fb1260a0c7276ba80b762793e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal462fb62fb1260a0c7276ba80b762793e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.lentera.menu-item','data' => ['href' => route('incentives.export', $period)]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('lentera.menu-item'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('incentives.export', $period))]); ?><?php echo e(__('ui.incentives.export')); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal462fb62fb1260a0c7276ba80b762793e)): ?>
<?php $attributes = $__attributesOriginal462fb62fb1260a0c7276ba80b762793e; ?>
<?php unset($__attributesOriginal462fb62fb1260a0c7276ba80b762793e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal462fb62fb1260a0c7276ba80b762793e)): ?>
<?php $component = $__componentOriginal462fb62fb1260a0c7276ba80b762793e; ?>
<?php unset($__componentOriginal462fb62fb1260a0c7276ba80b762793e); ?>
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

            <section class="rounded-lg bg-white p-6 shadow-soft">
                <dl class="grid gap-3 text-sm sm:grid-cols-2">
                    <div><dt class="text-gray-500"><?php echo e(__('ui.incentives.period')); ?></dt><dd class="font-medium"><?php echo e($period->period_start->format('d/m/Y')); ?> – <?php echo e($period->period_end->format('d/m/Y')); ?></dd></div>
                    <div><dt class="text-gray-500"><?php echo e(__('ui.incentives.branch')); ?></dt><dd class="font-medium"><?php echo e($period->branch?->name ?? '—'); ?></dd></div>
                    <div><dt class="text-gray-500"><?php echo e(__('ui.incentives.scope')); ?></dt><dd class="font-medium"><?php echo e($period->recipient?->name ?? __('ui.incentives.recipient_all')); ?></dd></div>
                    <div><dt class="text-gray-500"><?php echo e(__('ui.incentives.status')); ?></dt><dd class="font-medium"><?php echo e(__('ui.incentives.status_'.$period->status)); ?></dd></div>
                    <div><dt class="text-gray-500"><?php echo e(__('ui.incentives.transaction_total')); ?></dt><dd class="font-medium tnum"><?php echo e(\App\Support\Money::fromDecimal((string) $period->transaction_total)->format()); ?></dd></div>
                    <div><dt class="text-gray-500"><?php echo e(__('ui.incentives.total')); ?></dt><dd class="font-medium tnum"><?php echo e(\App\Support\Money::fromDecimal((string) $period->incentive_total)->format()); ?></dd></div>
                    <div><dt class="text-gray-500"><?php echo e(__('ui.incentives.adjustments_total')); ?></dt><dd class="font-medium tnum"><?php echo e($period->adjustmentsTotal()->format()); ?></dd></div>
                    <div><dt class="text-gray-500"><?php echo e(__('ui.incentives.net_total')); ?></dt><dd class="font-semibold tnum"><?php echo e($period->netTotal()->format()); ?></dd></div>
                    <?php if($period->approver): ?>
                        <div><dt class="text-gray-500"><?php echo e(__('ui.incentives.approved_by')); ?></dt><dd class="font-medium"><?php echo e($period->approver->name); ?> · <?php echo e($period->approved_at?->format('d/m/Y H:i')); ?></dd></div>
                    <?php endif; ?>
                    <?php if($period->payer): ?>
                        <div><dt class="text-gray-500"><?php echo e(__('ui.incentives.paid_by')); ?></dt><dd class="font-medium"><?php echo e($period->payer->name); ?> · <?php echo e($period->paid_at?->format('d/m/Y H:i')); ?></dd></div>
                    <?php endif; ?>
                </dl>
            </section>

            <section class="overflow-hidden rounded-lg bg-white shadow-soft">
                <h2 class="border-b border-gray-100 px-4 py-3 font-semibold text-gray-800"><?php echo e(__('ui.incentives.items')); ?></h2>
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.incentives.recipient')); ?></th>
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.incentives.sale')); ?></th>
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.incentives.product')); ?></th>
                            <th class="px-4 py-3 text-right font-medium"><?php echo e(__('ui.incentives.qty')); ?></th>
                            <th class="px-4 py-3 text-right font-medium"><?php echo e(__('ui.incentives.basis')); ?></th>
                            <th class="px-4 py-3 text-right font-medium"><?php echo e(__('ui.incentives.amount')); ?></th>
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.incentives.item_status')); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__empty_1 = true; $__currentLoopData = $period->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3"><?php echo e($item->recipient?->name); ?> <span class="text-xs text-gray-400">(<?php echo e($item->recipient_role); ?>)</span></td>
                                <td class="px-4 py-3"><?php echo e($item->sale?->number); ?></td>
                                <td class="px-4 py-3"><?php echo e($item->product_name ?? '—'); ?></td>
                                <td class="px-4 py-3 text-right tnum"><?php echo e($item->quantity); ?></td>
                                <td class="px-4 py-3 text-right tnum"><?php echo e(\App\Support\Money::fromDecimal((string) $item->item_final_amount)->format()); ?></td>
                                <td class="px-4 py-3 text-right tnum"><?php echo e(\App\Support\Money::fromDecimal((string) $item->incentive_amount)->format()); ?></td>
                                <td class="px-4 py-3">
                                    <?php if($item->status === 'eligible'): ?>
                                        <span class="inline-flex rounded-full bg-lentera-50 px-2 py-0.5 text-xs font-medium text-lentera-800 ring-1 ring-lentera-200"><?php echo e(__('ui.incentives.item_eligible')); ?></span>
                                    <?php else: ?>
                                        <span class="inline-flex rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700 ring-1 ring-red-200"><?php echo e(__('ui.incentives.item_cancelled')); ?></span>
                                        <?php if($item->cancellation_reason): ?><span class="mt-1 block text-xs text-gray-500"><?php echo e($item->cancellation_reason); ?></span><?php endif; ?>
                                    <?php endif; ?>
                                </td>
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
<?php $component->withAttributes([]); ?><?php echo e(__('ui.incentives.items_empty')); ?> <?php echo $__env->renderComponent(); ?>
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
            </section>

            <?php if($period->adjustments->isNotEmpty()): ?>
                <section class="overflow-hidden rounded-lg bg-white shadow-soft">
                    <h2 class="border-b border-gray-100 px-4 py-3 font-semibold text-gray-800"><?php echo e(__('ui.incentives.adjustments')); ?></h2>
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 text-left text-gray-500">
                                <th class="px-4 py-3 font-medium"><?php echo e(__('ui.incentives.recipient')); ?></th>
                                <th class="px-4 py-3 font-medium"><?php echo e(__('ui.incentives.product')); ?></th>
                                <th class="px-4 py-3 font-medium"><?php echo e(__('ui.incentives.reason')); ?></th>
                                <th class="px-4 py-3 text-right font-medium"><?php echo e(__('ui.incentives.amount')); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $__currentLoopData = $period->adjustments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $adjustment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <tr class="border-b border-gray-50 last:border-0">
                                    <td class="px-4 py-3"><?php echo e($adjustment->recipient?->name); ?> <span class="text-xs text-gray-400">(<?php echo e($adjustment->recipient_role); ?>)</span></td>
                                    <td class="px-4 py-3"><?php echo e($adjustment->product_name ?? '—'); ?> <span class="text-xs text-gray-400">· <?php echo e($adjustment->returned_quantity); ?> qty</span></td>
                                    <td class="px-4 py-3"><?php echo e($adjustment->reason); ?></td>
                                    <td class="px-4 py-3 text-right font-semibold text-red-700 tnum"><?php echo e(\App\Support\Money::fromDecimal((string) $adjustment->amount)->format()); ?></td>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </tbody>
                    </table>
                </section>
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
<?php /**PATH /home/hatch/workspace/lentera/resources/views/accounting/incentives/show.blade.php ENDPATH**/ ?>