<?php if(isset($charts)): ?>
    <div class="grid gap-4 lg:grid-cols-3">
        <div class="rounded-lg bg-white p-6 shadow-soft lg:col-span-2">
            <p class="text-sm font-medium text-gray-700"><?php echo e(__('ui.dashboard.trend_title')); ?></p>
            <p class="mb-3 text-xs text-gray-400"><?php echo e(__('ui.dashboard.trend_sub')); ?></p>
            <?php echo $charts['trend_svg']; ?>

        </div>
        <div class="rounded-lg bg-white p-6 shadow-soft">
            <p class="text-sm font-medium text-gray-700"><?php echo e(__('ui.dashboard.composition_title')); ?></p>
            <p class="mb-3 text-xs text-gray-400"><?php echo e(__('ui.dashboard.composition_sub')); ?></p>
            <div class="mx-auto max-w-[220px]"><?php echo $charts['composition_svg']; ?></div>
            <?php if($charts['legend'] === []): ?>
                <p class="mt-3 text-center text-sm text-gray-400"><?php echo e(__('ui.dashboard.no_data')); ?></p>
            <?php else: ?>
                <ul class="mt-3 space-y-1.5 text-sm">
                    <?php $__currentLoopData = $charts['legend']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <li class="flex items-center justify-between gap-2">
                            <span class="flex items-center gap-2 text-gray-600">
                                <span class="inline-block h-2.5 w-2.5 rounded-full" style="background-color: <?php echo e($row['color']); ?>"></span><?php echo e($row['label']); ?>

                            </span>
                            <span class="tnum text-gray-500"><?php echo e($row['value']->format()); ?> · <?php echo e(number_format($row['pct'], 1, ',', '.')); ?>%</span>
                        </li>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
<?php /**PATH /home/hatch/workspace/lentera/resources/views/dashboard/partials/charts.blade.php ENDPATH**/ ?>