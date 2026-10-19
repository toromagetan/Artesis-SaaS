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
        <h1 class="text-xl font-semibold leading-tight text-lentera-950"><?php echo e(__('ui.insurance_claims.title')); ?></h1>
     <?php $__env->endSlot(); ?>

    <div class="py-8">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <section class="overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.insurance_claims.sale')); ?></th>
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.insurance_claims.insurance')); ?></th>
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.insurance_claims.customer')); ?></th>
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.insurance_claims.branch')); ?></th>
                            <th class="px-4 py-3 text-right font-medium"><?php echo e(__('ui.insurance_claims.claim')); ?></th>
                            <th class="px-4 py-3 text-right font-medium"><?php echo e(__('ui.insurance_claims.settled')); ?></th>
                            <th class="px-4 py-3 text-right font-medium"><?php echo e(__('ui.insurance_claims.remaining')); ?></th>
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.insurance_claims.status')); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__empty_1 = true; $__currentLoopData = $usages; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $usage): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <tr class="border-b border-gray-50 last:border-0 hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <a href="<?php echo e(route('insurance-claims.show', $usage)); ?>" class="font-medium text-lentera-800 underline"><?php echo e($usage->sale->number); ?></a>
                                    <span class="block text-xs text-gray-400"><?php echo e($usage->used_at->format('d/m/Y')); ?></span>
                                </td>
                                <td class="px-4 py-3"><?php echo e($usage->insurance->name); ?></td>
                                <td class="px-4 py-3"><?php echo e($usage->customer->name); ?></td>
                                <td class="px-4 py-3"><?php echo e($usage->branch->name); ?></td>
                                <td class="px-4 py-3 text-right tnum"><?php echo e(\App\Support\Money::fromDecimal((string) $usage->insurance_claim_amount)->format()); ?></td>
                                <td class="px-4 py-3 text-right tnum"><?php echo e(\App\Support\Money::fromDecimal($usage->settledAmount())->format()); ?></td>
                                <td class="px-4 py-3 text-right font-medium tnum"><?php echo e(\App\Support\Money::fromDecimal($usage->remainingAmount())->format()); ?></td>
                                <td class="px-4 py-3">
                                    <?php if($usage->remainingAmount() === '0.00'): ?>
                                        <span class="inline-flex rounded-full bg-lentera-100 px-2.5 py-0.5 text-xs font-medium text-lentera-800"><?php echo e(__('ui.insurance_claims.settled_status')); ?></span>
                                    <?php else: ?>
                                        <span class="inline-flex rounded-full border border-gold/50 bg-gold/10 px-2.5 py-0.5 text-xs font-medium text-gold-dark"><?php echo e(__('ui.insurance_claims.open_status')); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <tr><td colspan="8"><?php if (isset($component)) { $__componentOriginal1fb10244e18bb2e5d2f72f1e8eb61469 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1fb10244e18bb2e5d2f72f1e8eb61469 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.lentera.empty-state','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('lentera.empty-state'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?><?php echo e(__('ui.insurance_claims.empty')); ?> <?php echo $__env->renderComponent(); ?>
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
                <div class="border-t border-gray-50 px-4 py-3"><?php echo e($usages->links()); ?></div>
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
<?php /**PATH /home/hatch/workspace/lentera/resources/views/sales/insurance-claims/index.blade.php ENDPATH**/ ?>