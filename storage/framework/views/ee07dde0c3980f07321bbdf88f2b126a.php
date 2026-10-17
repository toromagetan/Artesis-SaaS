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
        <h1 class="text-xl font-semibold leading-tight text-lentera-950"><?php echo e($po->number); ?></h1>
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
                <a href="<?php echo e(route('po.index')); ?>" class="text-sm text-gray-600 underline"><?php echo e(__('ui.back')); ?></a>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.lentera.menu-item','data' => ['href' => route('po.print', $po),'target' => '_blank']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('lentera.menu-item'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('po.print', $po)),'target' => '_blank']); ?><?php echo e(__('ui.purchasing.po_print')); ?> <?php echo $__env->renderComponent(); ?>
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
                    <?php if (! ($po->isVoid())): ?>
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
                            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('po.create')): ?>
                                <?php if (isset($component)) { $__componentOriginal462fb62fb1260a0c7276ba80b762793e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal462fb62fb1260a0c7276ba80b762793e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.lentera.menu-item','data' => ['href' => route('po.create')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('lentera.menu-item'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('po.create'))]); ?><?php echo e(__('ui.purchasing.po_create')); ?> <?php echo $__env->renderComponent(); ?>
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
                            <?php if($po->status === 'draft'): ?>
                                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('po.update')): ?>
                                    <form method="POST" action="<?php echo e(route('po.send', $po)); ?>">
                                        <?php echo csrf_field(); ?>
                                        <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-gray-700 transition duration-150 hover:bg-lentera-50 hover:text-lentera-900"><?php echo e(__('ui.purchasing.send')); ?></button>
                                    </form>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?php if(in_array($po->status, ['sent', 'partial'], true)): ?>
                                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('receipts.create')): ?>
                                    <?php if (isset($component)) { $__componentOriginal462fb62fb1260a0c7276ba80b762793e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal462fb62fb1260a0c7276ba80b762793e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.lentera.menu-item','data' => ['href' => route('po.receive', $po)]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('lentera.menu-item'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('po.receive', $po))]); ?><?php echo e(__('ui.purchasing.receive')); ?> <?php echo $__env->renderComponent(); ?>
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
                            <?php endif; ?>
                            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('po.void')): ?>
                                <div class="my-1 border-t border-gray-100"></div>
                                <form method="POST" action="<?php echo e(route('po.void', $po)); ?>" class="px-4 py-2" onsubmit="return confirm('<?php echo e(__('ui.purchasing.void')); ?>?')">
                                    <?php echo csrf_field(); ?>
                                    <input type="text" name="reason" required maxlength="255" placeholder="<?php echo e(__('ui.purchasing.void_reason')); ?>" class="mb-2 block w-56 rounded-md border-gray-300 text-sm shadow-sm focus:border-red-400 focus:ring-red-400">
                                    <button type="submit" class="text-sm text-red-600 hover:underline"><?php echo e(__('ui.purchasing.void')); ?></button>
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

            <?php if($po->isVoid()): ?>
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <?php echo e(__('ui.purchasing.po_statuses.void')); ?> — <?php echo e($po->void_reason); ?>

                </div>
            <?php endif; ?>

            <div class="mb-6 rounded-lg bg-white p-6 shadow-soft">
                <dl class="grid grid-cols-[160px_1fr] gap-y-3 text-sm">
                    <dt class="text-gray-500"><?php echo e(__('ui.purchasing.supplier')); ?></dt>
                    <dd class="font-medium text-gray-800"><?php echo e($po->supplier->name); ?></dd>
                    <dt class="text-gray-500"><?php echo e(__('ui.purchasing.destination_branch')); ?></dt>
                    <dd class="font-medium text-gray-800"><?php echo e($po->branch->name); ?></dd>
                    <dt class="text-gray-500"><?php echo e(__('ui.purchasing.date')); ?></dt>
                    <dd class="font-medium text-gray-800"><?php echo e($po->order_date->format('d/m/Y')); ?></dd>
                    <dt class="text-gray-500"><?php echo e(__('ui.purchasing.status')); ?></dt>
                    <dd class="font-medium text-gray-800"><?php echo e(__('ui.purchasing.po_statuses.'.$po->status)); ?></dd>
                    <dt class="text-gray-500"><?php echo e(__('ui.purchasing.total')); ?></dt>
                    <dd class="font-medium text-gray-800 tnum"><?php echo e(\App\Support\Money::fromDecimal((string) $po->total_amount)->format()); ?></dd>
                </dl>
            </div>

            <h2 class="mb-2 text-base font-semibold text-gray-800"><?php echo e(__('ui.purchasing.items')); ?></h2>
            <div class="mb-6 overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.purchasing.product')); ?></th>
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.purchasing.qty_ordered')); ?></th>
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.purchasing.qty_received')); ?></th>
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.purchasing.qty_remaining')); ?></th>
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.purchasing.unit_cost')); ?></th>
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.purchasing.line_total')); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__currentLoopData = $po->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 font-medium text-gray-800"><?php echo e($item->product->name); ?></td>
                                <td class="px-4 py-3 tnum"><?php echo e($item->qty_ordered); ?></td>
                                <td class="px-4 py-3 tnum"><?php echo e($item->qty_received); ?></td>
                                <td class="px-4 py-3 tnum"><?php echo e($item->remainingQty()); ?></td>
                                <td class="px-4 py-3 tnum"><?php echo e(\App\Support\Money::fromDecimal((string) $item->unit_cost)->format()); ?></td>
                                <td class="px-4 py-3 tnum"><?php echo e(\App\Support\Money::fromDecimal((string) $item->line_total)->format()); ?></td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </tbody>
                </table>
            </div>

            <h2 class="mb-2 text-base font-semibold text-gray-800"><?php echo e(__('ui.transfer.distribution')); ?></h2>
            <div class="mb-6 rounded-lg bg-white p-6 shadow-soft">
                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('stock.transfer')): ?>
                    <form method="POST" action="<?php echo e(route('po.transfer.store', $po)); ?>">
                        <?php echo csrf_field(); ?>
                        <div class="space-y-2">
                            <?php $__currentLoopData = $po->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <div class="grid grid-cols-12 items-end gap-2">
                                    <div class="col-span-7 text-sm">
                                        <span class="font-medium text-gray-800"><?php echo e($item->product->name); ?></span>
                                        <span class="block text-xs text-gray-400"><?php echo e(__('ui.transfer.available')); ?>: <?php echo e($distributable[$item->product_id] ?? 0); ?></span>
                                    </div>
                                    <input type="hidden" name="items[<?php echo e($i); ?>][product_id]" value="<?php echo e($item->product_id); ?>">
                                    <div class="col-span-5">
                                        <?php if (isset($component)) { $__componentOriginal18c21970322f9e5c938bc954620c12bb = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal18c21970322f9e5c938bc954620c12bb = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.text-input','data' => ['name' => 'items['.e($i).'][qty]','type' => 'number','min' => '0','max' => ''.e($distributable[$item->product_id] ?? 0).'','class' => 'block w-full tnum','placeholder' => 'Qty kirim']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('text-input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'items['.e($i).'][qty]','type' => 'number','min' => '0','max' => ''.e($distributable[$item->product_id] ?? 0).'','class' => 'block w-full tnum','placeholder' => 'Qty kirim']); ?>
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
                                    </div>
                                </div>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </div>
                        <div class="mt-4 flex flex-wrap items-end gap-3">
                            <div>
                                <?php if (isset($component)) { $__componentOriginale3da9d84bb64e4bc2eeebaafabfb2581 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale3da9d84bb64e4bc2eeebaafabfb2581 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.input-label','data' => ['for' => 'tujuan','value' => __('ui.transfer.to')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('input-label'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['for' => 'tujuan','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.transfer.to'))]); ?>
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
                                <select id="tujuan" name="tujuan" class="mt-1 block w-64 rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500" required>
                                    <?php $__currentLoopData = $branches; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $branch): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <?php if (! ($branch->id === $po->branch_id)): ?>
                                            <option value="<?php echo e($branch->id); ?>"><?php echo e($branch->name); ?></option>
                                        <?php endif; ?>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </select>
                            </div>
                            <?php if (isset($component)) { $__componentOriginald411d1792bd6cc877d687758b753742c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald411d1792bd6cc877d687758b753742c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.primary-button','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('primary-button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?><?php echo e(__('ui.transfer.send')); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald411d1792bd6cc877d687758b753742c)): ?>
<?php $attributes = $__attributesOriginald411d1792bd6cc877d687758b753742c; ?>
<?php unset($__attributesOriginald411d1792bd6cc877d687758b753742c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald411d1792bd6cc877d687758b753742c)): ?>
<?php $component = $__componentOriginald411d1792bd6cc877d687758b753742c; ?>
<?php unset($__componentOriginald411d1792bd6cc877d687758b753742c); ?>
<?php endif; ?>
                        </div>
                    </form>
                <?php endif; ?>

                <?php if($transfers->isNotEmpty()): ?>
                    <h3 class="mb-2 mt-6 text-sm font-semibold text-gray-700"><?php echo e(__('ui.transfer.distribution_history')); ?></h3>
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 text-left text-gray-500">
                                <th class="py-2 pe-4 font-medium"><?php echo e(__('ui.transfer.number')); ?></th>
                                <th class="py-2 pe-4 font-medium"><?php echo e(__('ui.transfer.to')); ?></th>
                                <th class="py-2 pe-4 font-medium"><?php echo e(__('ui.transfer.items')); ?></th>
                                <th class="py-2 font-medium"><?php echo e(__('ui.transfer.status')); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $__currentLoopData = $transfers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $transfer): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <tr class="border-b border-gray-50 last:border-0">
                                    <td class="py-2 pe-4 font-medium"><a href="<?php echo e(route('transfers.show', $transfer)); ?>" class="text-lentera-800 underline-offset-2 hover:underline"><?php echo e($transfer->number); ?></a></td>
                                    <td class="py-2 pe-4"><?php echo e($transfer->toBranch->name); ?></td>
                                    <td class="py-2 pe-4"><?php echo e($transfer->items->map(fn ($i) => $i->product->name.' ×'.$i->qty)->join(', ')); ?></td>
                                    <td class="py-2"><?php echo e(__('ui.transfer.statuses.'.$transfer->status)); ?></td>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <h2 class="mb-2 text-base font-semibold text-gray-800"><?php echo e(__('ui.purchasing.receipts')); ?></h2>
            <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.purchasing.receipt_number')); ?></th>
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.purchasing.received_date')); ?></th>
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.purchasing.delivery_note')); ?></th>
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.purchasing.total')); ?></th>
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.purchasing.remaining')); ?></th>
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.purchasing.pay_status')); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__empty_1 = true; $__currentLoopData = $po->receipts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $receipt): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 font-medium"><a href="<?php echo e(route('receipts.show', $receipt)); ?>" class="text-lentera-800 underline-offset-2 hover:underline"><?php echo e($receipt->number); ?></a></td>
                                <td class="px-4 py-3"><?php echo e($receipt->received_date->format('d/m/Y')); ?></td>
                                <td class="px-4 py-3"><?php echo e($receipt->delivery_note_no ?: '—'); ?></td>
                                <td class="px-4 py-3 tnum"><?php echo e($receipt->totalAmount()->format()); ?></td>
                                <td class="px-4 py-3 tnum"><?php echo e($receipt->remainingAmount()->format()); ?></td>
                                <td class="px-4 py-3"><?php echo e(__('ui.purchasing.pay_statuses.'.$receipt->paymentStatus())); ?></td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <tr><td colspan="6"><?php if (isset($component)) { $__componentOriginal1fb10244e18bb2e5d2f72f1e8eb61469 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1fb10244e18bb2e5d2f72f1e8eb61469 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.lentera.empty-state','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('lentera.empty-state'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?><?php echo e(__('ui.purchasing.empty_receipts')); ?> <?php echo $__env->renderComponent(); ?>
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
<?php /**PATH /home/hatch/workspace/lentera/resources/views/purchasing/orders/show.blade.php ENDPATH**/ ?>