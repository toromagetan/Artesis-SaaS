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
        <h1 class="text-xl font-semibold leading-tight text-lentera-950"><?php echo e(__('ui.dashboard.title')); ?></h1>
     <?php $__env->endSlot(); ?>

    <div class="py-8">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <?php if(empty($cards)): ?>
                <div class="rounded-lg bg-white shadow-soft"><?php if (isset($component)) { $__componentOriginal1fb10244e18bb2e5d2f72f1e8eb61469 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1fb10244e18bb2e5d2f72f1e8eb61469 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.lentera.empty-state','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('lentera.empty-state'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?><?php echo e(__('ui.dashboard.empty')); ?> <?php echo $__env->renderComponent(); ?>
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

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <?php if(isset($cards['sales'])): ?>
                    <a href="<?php echo e(route('sales.index')); ?>" class="rounded-lg bg-white p-6 shadow-soft transition duration-150 hover:shadow">
                        <p class="text-sm text-gray-500"><?php echo e(__('ui.dashboard.sales_today')); ?></p>
                        <p class="mt-1 text-2xl font-semibold tnum"><?php echo e($cards['sales']['today_total']->format()); ?></p>
                        <p class="text-sm text-gray-400"><?php echo e($cards['sales']['today_count']); ?> <?php echo e(__('ui.dashboard.transactions')); ?></p>
                        <p class="mt-3 text-sm text-gray-500"><?php echo e(__('ui.dashboard.receivable')); ?>: <span class="font-medium tnum"><?php echo e($cards['sales']['receivable']->format()); ?></span></p>
                    </a>
                <?php endif; ?>

                <?php if(isset($cards['lab'])): ?>
                    <a href="<?php echo e(route('lab.index')); ?>" class="rounded-lg bg-white p-6 shadow-soft transition duration-150 hover:shadow">
                        <p class="text-sm text-gray-500"><?php echo e(__('ui.dashboard.lab_queue')); ?></p>
                        <p class="mt-1 text-2xl font-semibold tnum"><?php echo e($cards['lab']['waiting'] + $cards['lab']['edging'] + $cards['lab']['qc'] + $cards['lab']['ready']); ?></p>
                        <p class="text-sm text-gray-400">
                            <?php echo e(__('ui.dashboard.lab_waiting')); ?> <?php echo e($cards['lab']['waiting']); ?> ·
                            <?php echo e(__('ui.dashboard.lab_edging')); ?> <?php echo e($cards['lab']['edging']); ?> ·
                            <?php echo e(__('ui.dashboard.lab_qc')); ?> <?php echo e($cards['lab']['qc']); ?> ·
                            <?php echo e(__('ui.dashboard.lab_ready')); ?> <?php echo e($cards['lab']['ready']); ?>

                        </p>
                    </a>
                <?php endif; ?>

                <?php if(isset($cards['stock'])): ?>
                    <a href="<?php echo e(route('stock.index')); ?>" class="rounded-lg bg-white p-6 shadow-soft transition duration-150 hover:shadow">
                        <p class="text-sm text-gray-500"><?php echo e(__('ui.dashboard.stock_alerts')); ?></p>
                        <p class="mt-1 text-2xl font-semibold tnum"><?php echo e($cards['stock']['low'] + $cards['stock']['out'] + $cards['stock']['backorder']); ?></p>
                        <p class="text-sm text-gray-400">
                            <?php echo e(__('ui.dashboard.stock_low')); ?> <?php echo e($cards['stock']['low']); ?> ·
                            <?php echo e(__('ui.dashboard.stock_out')); ?> <?php echo e($cards['stock']['out']); ?> ·
                            <?php echo e(__('ui.dashboard.stock_backorder')); ?> <?php echo e($cards['stock']['backorder']); ?>

                        </p>
                        <p class="mt-3 text-sm text-gray-500"><?php echo e(__('ui.dashboard.transfers_in')); ?>: <span class="font-medium tnum"><?php echo e($cards['transfers_in']); ?></span></p>
                    </a>
                    <?php if(! empty($cards['stock']['expiring'])): ?>
                        <div class="rounded-lg border border-gold/50 bg-gold/10 p-6">
                            <p class="text-sm font-medium text-gold-dark"><?php echo e(__('ui.dashboard.expiring_title')); ?></p>
                            <ul class="mt-2 space-y-1 text-sm text-gold-dark">
                                <?php $__currentLoopData = array_slice($cards['stock']['expiring'], 0, 5); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <li class="flex items-center justify-between gap-2">
                                        <span><?php echo e($row['product_name']); ?> · <?php echo e($row['branch_name']); ?></span>
                                        <span class="font-medium tnum"><?php echo e($row['qty']); ?> · <?php echo e(\Illuminate\Support\Carbon::parse($row['expiry_date'])->format('d/m/Y')); ?><?php if($row['expired']): ?> (<?php echo e(__('ui.dashboard.expired')); ?>)<?php endif; ?></span>
                                    </li>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if(isset($cards['pr_pending'])): ?>
                    <a href="<?php echo e(route('pr.index')); ?>" class="rounded-lg bg-white p-6 shadow-soft transition duration-150 hover:shadow">
                        <p class="text-sm text-gray-500"><?php echo e(__('ui.dashboard.pr_pending')); ?></p>
                        <p class="mt-1 text-2xl font-semibold tnum"><?php echo e($cards['pr_pending']); ?></p>
                    </a>
                <?php endif; ?>

                <?php if(isset($cards['payables'])): ?>
                    <a href="<?php echo e(route('reports.payables')); ?>" class="rounded-lg bg-white p-6 shadow-soft transition duration-150 hover:shadow">
                        <p class="text-sm text-gray-500"><?php echo e(__('ui.dashboard.payables')); ?></p>
                        <p class="mt-1 text-2xl font-semibold tnum"><?php echo e($cards['payables']->format()); ?></p>
                    </a>
                <?php endif; ?>

                <?php if(isset($cards['finance'])): ?>
                    <div class="rounded-lg bg-white p-6 shadow-soft">
                        <p class="text-sm text-gray-500"><?php echo e(__('ui.dashboard.finance')); ?></p>
                        <p class="mt-1 text-2xl font-semibold tnum"><?php echo e($cards['finance']['month_net']->format()); ?></p>
                        <p class="text-sm text-gray-400"><?php echo e(__('ui.dashboard.month_net')); ?></p>
                        <?php if($cards['finance']['cash'] !== null): ?>
                            <p class="mt-3 text-sm text-gray-500"><?php echo e(__('ui.dashboard.cash_position')); ?>: <span class="font-medium tnum"><?php echo e($cards['finance']['cash']->format()); ?></span></p>
                        <?php endif; ?>
                        <?php if(! empty($cards['finance']['by_branch'])): ?>
                            <ul class="mt-3 space-y-1 border-t border-gray-50 pt-3 text-sm">
                                <?php $__currentLoopData = $cards['finance']['by_branch']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <li class="flex items-center justify-between">
                                        <span class="text-gray-500"><?php echo e($row['branch_id'] === null ? __('ui.financial.no_branch') : ($cards['finance']['branch_names'][$row['branch_id']] ?? '#'.$row['branch_id'])); ?></span>
                                        <span class="font-medium tnum"><?php echo e($row['net']->format()); ?></span>
                                    </li>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </ul>
                        <?php endif; ?>
                        <p class="mt-3"><a href="<?php echo e(route('financial.income')); ?>" class="text-sm text-lentera-800 underline"><?php echo e(__('ui.financial.income_statement')); ?></a></p>
                    </div>
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
<?php /**PATH /home/hatch/workspace/lentera/resources/views/dashboard.blade.php ENDPATH**/ ?>