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
        <h1 class="text-xl font-semibold leading-tight text-lentera-950"><?php echo e($transfer->number); ?></h1>
     <?php $__env->endSlot(); ?>

    <div class="py-8">
        <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
            <?php if(session('status')): ?>
                <div class="mb-4 rounded-lg border border-lentera-200 bg-lentera-50 px-4 py-3 text-sm text-lentera-800"><?php echo e(session('status')); ?></div>
            <?php endif; ?>
            <?php if($errors->any()): ?>
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><div><?php echo e($error); ?></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            <?php endif; ?>

            <div class="mb-4 flex items-center justify-between">
                <a href="<?php echo e(route('transfers.index')); ?>" class="text-sm text-gray-600 underline"><?php echo e(__('ui.back')); ?></a>
                <div class="flex items-center gap-2">
                    <?php if (isset($component)) { $__componentOriginalf727e0c314d4e62a29253ce63c58be56 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf727e0c314d4e62a29253ce63c58be56 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.lentera.dropdown-cetak','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('lentera.dropdown-cetak'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
                        <?php if (isset($component)) { $__componentOriginal462fb62fb1260a0c7276ba80b762793e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal462fb62fb1260a0c7276ba80b762793e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.lentera.menu-item','data' => ['href' => route('transfers.print', $transfer),'target' => '_blank']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('lentera.menu-item'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('transfers.print', $transfer)),'target' => '_blank']); ?><?php echo e(__('ui.transfer.print')); ?> <?php echo $__env->renderComponent(); ?>
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
<?php if (isset($__attributesOriginalf727e0c314d4e62a29253ce63c58be56)): ?>
<?php $attributes = $__attributesOriginalf727e0c314d4e62a29253ce63c58be56; ?>
<?php unset($__attributesOriginalf727e0c314d4e62a29253ce63c58be56); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalf727e0c314d4e62a29253ce63c58be56)): ?>
<?php $component = $__componentOriginalf727e0c314d4e62a29253ce63c58be56; ?>
<?php unset($__componentOriginalf727e0c314d4e62a29253ce63c58be56); ?>
<?php endif; ?>
                    <?php if($transfer->status === 'sent'): ?>
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
                            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('stock.receive_transfer')): ?>
                                <form method="POST" action="<?php echo e(route('transfers.receive', $transfer)); ?>">
                                    <?php echo csrf_field(); ?>
                                    <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-gray-700 transition duration-150 hover:bg-lentera-50 hover:text-lentera-900"><?php echo e(__('ui.transfer.receive')); ?></button>
                                </form>
                            <?php endif; ?>
                            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('stock.transfer')): ?>
                                <div class="my-1 border-t border-gray-100"></div>
                                <form method="POST" action="<?php echo e(route('transfers.cancel', $transfer)); ?>" onsubmit="return confirm('<?php echo e(__('ui.transfer.cancel')); ?>?')">
                                    <?php echo csrf_field(); ?>
                                    <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-red-600 transition duration-150 hover:bg-red-50"><?php echo e(__('ui.transfer.cancel')); ?></button>
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
                    <?php endif; ?>
                </div>
            </div>

            <div class="mb-6 rounded-lg bg-white p-6 shadow-soft">
                <dl class="grid grid-cols-[160px_1fr] gap-y-3 text-sm">
                    <dt class="text-gray-500"><?php echo e(__('ui.transfer.from')); ?></dt>
                    <dd class="font-medium text-gray-800"><?php echo e($transfer->fromBranch->name); ?></dd>
                    <dt class="text-gray-500"><?php echo e(__('ui.transfer.to')); ?></dt>
                    <dd class="font-medium text-gray-800"><?php echo e($transfer->toBranch->name); ?></dd>
                    <dt class="text-gray-500"><?php echo e(__('ui.transfer.source')); ?></dt>
                    <dd class="font-medium text-gray-800">
                        <?php if($transfer->source_type === 'purchase_order' && $transfer->purchaseOrder): ?>
                            <?php echo e(__('ui.transfer.source_po')); ?> — <a class="text-lentera-800 underline-offset-2 hover:underline" href="<?php echo e(route('po.show', $transfer->purchaseOrder)); ?>"><?php echo e($transfer->purchaseOrder->number); ?></a>
                        <?php else: ?>
                            <?php echo e(__('ui.transfer.source_stock')); ?>

                        <?php endif; ?>
                    </dd>
                    <dt class="text-gray-500"><?php echo e(__('ui.transfer.status')); ?></dt>
                    <dd class="font-medium text-gray-800"><?php echo e(__('ui.transfer.statuses.'.$transfer->status)); ?></dd>
                    <dt class="text-gray-500"><?php echo e(__('ui.transfer.sender')); ?></dt>
                    <dd class="font-medium text-gray-800"><?php echo e($transfer->sender->name); ?> · <?php echo e($transfer->created_at->format('d/m/Y H:i')); ?></dd>
                    <?php if($transfer->receiver): ?>
                        <dt class="text-gray-500"><?php echo e(__('ui.transfer.receiver')); ?></dt>
                        <dd class="font-medium text-gray-800"><?php echo e($transfer->receiver->name); ?> · <?php echo e($transfer->received_at?->format('d/m/Y H:i')); ?></dd>
                    <?php endif; ?>
                    <?php if($transfer->notes): ?>
                        <dt class="text-gray-500"><?php echo e(__('ui.purchasing.notes')); ?></dt>
                        <dd class="font-medium text-gray-800"><?php echo e($transfer->notes); ?></dd>
                    <?php endif; ?>
                </dl>
            </div>

            <h2 class="mb-2 text-base font-semibold text-gray-800"><?php echo e(__('ui.transfer.items')); ?></h2>
            <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.purchasing.product')); ?></th>
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.purchasing.qty')); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__currentLoopData = $transfer->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 font-medium text-gray-800"><?php echo e($item->product->name); ?></td>
                                <td class="px-4 py-3 tnum"><?php echo e($item->qty); ?></td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </tbody>
                </table>
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
<?php /**PATH /home/hatch/workspace/lentera/resources/views/inventory/transfers/show.blade.php ENDPATH**/ ?>