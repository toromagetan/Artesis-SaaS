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
        <h1 class="text-xl font-semibold leading-tight text-lentera-950"><?php echo e($sale->number); ?></h1>
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
                <a href="<?php echo e(route('sales.index')); ?>" class="text-sm text-gray-600 underline"><?php echo e(__('ui.back')); ?></a>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.lentera.menu-item','data' => ['href' => route('sales.print-order', $sale),'target' => '_blank']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('lentera.menu-item'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('sales.print-order', $sale)),'target' => '_blank']); ?><?php echo e(__('ui.sales.print_order')); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal462fb62fb1260a0c7276ba80b762793e)): ?>
<?php $attributes = $__attributesOriginal462fb62fb1260a0c7276ba80b762793e; ?>
<?php unset($__attributesOriginal462fb62fb1260a0c7276ba80b762793e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal462fb62fb1260a0c7276ba80b762793e)): ?>
<?php $component = $__componentOriginal462fb62fb1260a0c7276ba80b762793e; ?>
<?php unset($__componentOriginal462fb62fb1260a0c7276ba80b762793e); ?>
<?php endif; ?>
                        <?php if (isset($component)) { $__componentOriginal462fb62fb1260a0c7276ba80b762793e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal462fb62fb1260a0c7276ba80b762793e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.lentera.menu-item','data' => ['href' => route('sales.print-invoice', $sale),'target' => '_blank']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('lentera.menu-item'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('sales.print-invoice', $sale)),'target' => '_blank']); ?><?php echo e(__('ui.sales.print_invoice')); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal462fb62fb1260a0c7276ba80b762793e)): ?>
<?php $attributes = $__attributesOriginal462fb62fb1260a0c7276ba80b762793e; ?>
<?php unset($__attributesOriginal462fb62fb1260a0c7276ba80b762793e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal462fb62fb1260a0c7276ba80b762793e)): ?>
<?php $component = $__componentOriginal462fb62fb1260a0c7276ba80b762793e; ?>
<?php unset($__componentOriginal462fb62fb1260a0c7276ba80b762793e); ?>
<?php endif; ?>
                        <?php if($sale->prescription_snapshot): ?>
                            <?php if (isset($component)) { $__componentOriginal462fb62fb1260a0c7276ba80b762793e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal462fb62fb1260a0c7276ba80b762793e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.lentera.menu-item','data' => ['href' => route('sales.print-prescription', $sale),'target' => '_blank']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('lentera.menu-item'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('sales.print-prescription', $sale)),'target' => '_blank']); ?><?php echo e(__('ui.sales.print_prescription')); ?> <?php echo $__env->renderComponent(); ?>
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
<?php if (isset($__attributesOriginalf727e0c314d4e62a29253ce63c58be56)): ?>
<?php $attributes = $__attributesOriginalf727e0c314d4e62a29253ce63c58be56; ?>
<?php unset($__attributesOriginalf727e0c314d4e62a29253ce63c58be56); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalf727e0c314d4e62a29253ce63c58be56)): ?>
<?php $component = $__componentOriginalf727e0c314d4e62a29253ce63c58be56; ?>
<?php unset($__componentOriginalf727e0c314d4e62a29253ce63c58be56); ?>
<?php endif; ?>
                    <?php if (! ($sale->isVoid())): ?>
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
                            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('sales.create')): ?>
                                <?php if (isset($component)) { $__componentOriginal462fb62fb1260a0c7276ba80b762793e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal462fb62fb1260a0c7276ba80b762793e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.lentera.menu-item','data' => ['href' => route('sales.create', ['pelanggan' => $sale->customer_id])]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('lentera.menu-item'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('sales.create', ['pelanggan' => $sale->customer_id]))]); ?><?php echo e(__('ui.sales.create')); ?> <?php echo $__env->renderComponent(); ?>
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
                            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('sales.pay')): ?>
                                <?php if($sale->paymentStatus() !== 'paid'): ?>
                                    <?php if (isset($component)) { $__componentOriginal462fb62fb1260a0c7276ba80b762793e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal462fb62fb1260a0c7276ba80b762793e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.lentera.menu-item','data' => ['href' => route('sales.payment', $sale)]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('lentera.menu-item'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('sales.payment', $sale))]); ?><?php echo e(__('ui.sales.add_payment')); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal462fb62fb1260a0c7276ba80b762793e)): ?>
<?php $attributes = $__attributesOriginal462fb62fb1260a0c7276ba80b762793e; ?>
<?php unset($__attributesOriginal462fb62fb1260a0c7276ba80b762793e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal462fb62fb1260a0c7276ba80b762793e)): ?>
<?php $component = $__componentOriginal462fb62fb1260a0c7276ba80b762793e; ?>
<?php unset($__componentOriginal462fb62fb1260a0c7276ba80b762793e); ?>
<?php endif; ?>
                                    <?php if($sale->installments->isEmpty()): ?>
                                        <?php if (isset($component)) { $__componentOriginal462fb62fb1260a0c7276ba80b762793e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal462fb62fb1260a0c7276ba80b762793e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.lentera.menu-item','data' => ['href' => route('sales.installment', $sale)]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('lentera.menu-item'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('sales.installment', $sale))]); ?><?php echo e(__('ui.sales.create_schedule')); ?> <?php echo $__env->renderComponent(); ?>
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
                            <?php endif; ?>
                            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('sales.return')): ?>
                                <?php if (isset($component)) { $__componentOriginal462fb62fb1260a0c7276ba80b762793e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal462fb62fb1260a0c7276ba80b762793e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.lentera.menu-item','data' => ['href' => route('sales.return', $sale)]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('lentera.menu-item'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('sales.return', $sale))]); ?><?php echo e(__('ui.sales.make_return')); ?> <?php echo $__env->renderComponent(); ?>
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
                            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('sales.void')): ?>
                                <div class="my-1 border-t border-gray-100"></div>
                                <?php if (isset($component)) { $__componentOriginal462fb62fb1260a0c7276ba80b762793e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal462fb62fb1260a0c7276ba80b762793e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.lentera.menu-item','data' => ['href' => route('sales.void', $sale),'danger' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('lentera.menu-item'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('sales.void', $sale)),'danger' => true]); ?><?php echo e(__('ui.sales.void')); ?> <?php echo $__env->renderComponent(); ?>
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
                    <?php endif; ?>
                </div>
            </div>

            <?php if($sale->isVoid()): ?>
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <?php echo e(__('ui.sales.status_void')); ?> — <?php echo e($sale->void_reason); ?> (<?php echo e($sale->voided_at?->format('d/m/Y H:i')); ?>)
                </div>
            <?php endif; ?>

            <div class="mb-6 rounded-lg bg-white p-6 shadow-soft">
                <dl class="grid grid-cols-[160px_1fr] gap-y-3 text-sm">
                    <dt class="text-gray-500"><?php echo e(__('ui.sales.customer')); ?></dt>
                    <dd class="font-medium text-gray-800"><a class="text-lentera-800 underline-offset-2 hover:underline" href="<?php echo e(route('customers.show', $sale->customer)); ?>"><?php echo e($sale->customer->name); ?></a> · <?php echo e($sale->customer->phone); ?></dd>
                    <dt class="text-gray-500"><?php echo e(__('ui.sales.date')); ?></dt>
                    <dd class="font-medium text-gray-800"><?php echo e($sale->sale_date->format('d/m/Y')); ?></dd>
                    <dt class="text-gray-500"><?php echo e(__('ui.sales.branch')); ?></dt>
                    <dd class="font-medium text-gray-800"><?php echo e($sale->branch->name); ?></dd>
                    <dt class="text-gray-500"><?php echo e(__('ui.sales.cashier')); ?></dt>
                    <dd class="font-medium text-gray-800"><?php echo e($sale->cashier->name); ?></dd>
                    <dt class="text-gray-500"><?php echo e(__('ui.sales.pay_status')); ?></dt>
                    <dd class="font-medium text-gray-800"><?php echo e(__('ui.sales.pay_'.$sale->paymentStatus())); ?></dd>
                    <?php if($procurementStage !== null): ?>
                        <dt class="text-gray-500"><?php echo e(__('ui.lab.lens_status')); ?></dt>
                        <dd class="font-medium text-gray-800">
                            <?php echo e(__('ui.lab.stages.'.$procurementStage)); ?>

                            <?php $__currentLoopData = $linkedPrs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $linkedPr): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                · <a class="text-lentera-800 underline-offset-2 hover:underline" href="<?php echo e(route('pr.show', $linkedPr)); ?>"><?php echo e($linkedPr->number); ?></a>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </dd>
                    <?php endif; ?>
                    <?php if($sale->doctor): ?>
                        <dt class="text-gray-500"><?php echo e(__('ui.sales.doctor')); ?></dt>
                        <dd class="font-medium text-gray-800"><?php echo e($sale->doctor->name); ?></dd>
                    <?php endif; ?>
                    <?php if($sale->notes): ?>
                        <dt class="text-gray-500"><?php echo e(__('ui.sales.notes')); ?></dt>
                        <dd class="font-medium text-gray-800"><?php echo e($sale->notes); ?></dd>
                    <?php endif; ?>
                </dl>
            </div>

            <?php if($sale->prescription_snapshot): ?>
                <h2 class="mb-2 text-base font-semibold text-gray-800"><?php echo e(__('ui.sales.prescription')); ?></h2>
                <div class="mb-6 rounded-lg bg-white p-6 shadow-soft">
                    <div class="grid gap-4 md:grid-cols-2">
                        <?php $__currentLoopData = ['od' => 'eye_right', 'os' => 'eye_left']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $side => $labelKey): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <div class="rounded-lg border border-gray-200 p-4">
                                <h3 class="mb-3 text-sm font-semibold text-lentera-900"><?php echo e(__('ui.prescriptions.'.$labelKey)); ?></h3>
                                <dl class="grid grid-cols-2 gap-y-2 text-sm tnum">
                                    <dt class="text-gray-500"><?php echo e(__('ui.prescriptions.sph')); ?></dt>
                                    <dd class="font-medium text-gray-800"><?php echo e($sale->prescription_snapshot[$side]['sph']); ?></dd>
                                    <dt class="text-gray-500"><?php echo e(__('ui.prescriptions.cyl')); ?></dt>
                                    <dd class="font-medium text-gray-800"><?php echo e($sale->prescription_snapshot[$side]['cyl']); ?></dd>
                                    <dt class="text-gray-500"><?php echo e(__('ui.prescriptions.axis')); ?></dt>
                                    <dd class="font-medium text-gray-800"><?php echo e($sale->prescription_snapshot[$side]['axis'] ?? '—'); ?></dd>
                                    <dt class="text-gray-500"><?php echo e(__('ui.prescriptions.add')); ?></dt>
                                    <dd class="font-medium text-gray-800"><?php echo e($sale->prescription_snapshot[$side]['add'] ?? '—'); ?></dd>
                                </dl>
                            </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                </div>
            <?php endif; ?>

            <h2 class="mb-2 text-base font-semibold text-gray-800"><?php echo e(__('ui.sales.items')); ?></h2>
            <div class="mb-6 overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.sales.item')); ?></th>
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.sales.qty')); ?></th>
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.sales.unit_price')); ?></th>
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.sales.discount')); ?></th>
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.sales.line_total')); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__currentLoopData = $sale->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 font-medium text-gray-800"><?php echo e($item->product_name_snapshot); ?> <span class="text-xs text-gray-400">(<?php echo e(__('ui.products.types.'.$item->type)); ?><?php echo e($item->prescription_ref ? ' · '.strtoupper($item->prescription_ref) : ''); ?>)</span></td>
                                <td class="px-4 py-3 tnum"><?php echo e($item->qty); ?></td>
                                <td class="px-4 py-3 tnum"><?php echo e(\App\Support\Money::fromDecimal((string) $item->unit_price)->format()); ?></td>
                                <td class="px-4 py-3 tnum"><?php echo e(\App\Support\Money::fromDecimal((string) $item->discount_amount)->format()); ?></td>
                                <td class="px-4 py-3 tnum"><?php echo e(\App\Support\Money::fromDecimal((string) $item->line_total)->format()); ?></td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </tbody>
                    <tfoot>
                        <tr class="text-sm">
                            <td colspan="4" class="px-4 py-2 text-right text-gray-500"><?php echo e(__('ui.sales.subtotal')); ?></td>
                            <td class="px-4 py-2 tnum font-medium"><?php echo e(\App\Support\Money::fromDecimal((string) $sale->subtotal_amount)->format()); ?></td>
                        </tr>
                        <tr class="text-sm">
                            <td colspan="4" class="px-4 py-2 text-right text-gray-500"><?php echo e(__('ui.sales.discount')); ?></td>
                            <td class="px-4 py-2 tnum font-medium">−<?php echo e(\App\Support\Money::fromDecimal((string) $sale->discount_amount)->format()); ?></td>
                        </tr>
                        <tr class="text-sm">
                            <td colspan="4" class="px-4 py-2 text-right text-gray-500"><?php echo e(__('ui.sales.tax')); ?> (<?php echo e($sale->tax_mode); ?>)</td>
                            <td class="px-4 py-2 tnum font-medium"><?php echo e(\App\Support\Money::fromDecimal((string) $sale->tax_amount)->format()); ?></td>
                        </tr>
                        <tr class="text-sm font-semibold">
                            <td colspan="4" class="px-4 py-2 text-right"><?php echo e(__('ui.sales.total')); ?></td>
                            <td class="px-4 py-2 tnum"><?php echo e(\App\Support\Money::fromDecimal((string) $sale->total_amount)->format()); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <h2 class="mb-2 text-base font-semibold text-gray-800"><?php echo e(__('ui.sales.payments')); ?></h2>
            <div class="mb-6 overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.sales.date')); ?></th>
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.sales.method')); ?></th>
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.sales.amount')); ?></th>
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.sales.reference')); ?></th>
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.sales.received_by')); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__empty_1 = true; $__currentLoopData = $sale->payments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $payment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <tr class="border-b border-gray-50 last:border-0 <?php echo e($payment->amount < 0 ? 'text-red-600' : ''); ?>">
                                <td class="px-4 py-3"><?php echo e($payment->paid_at->format('d/m/Y H:i')); ?></td>
                                <td class="px-4 py-3"><?php echo e(__('ui.sales.methods.'.$payment->method)); ?><?php echo e($payment->isReversal() ? ' ↩' : ''); ?></td>
                                <td class="px-4 py-3 tnum"><?php echo e(\App\Support\Money::fromDecimal((string) $payment->amount)->format()); ?></td>
                                <td class="px-4 py-3"><?php echo e($payment->reference ?: '—'); ?></td>
                                <td class="px-4 py-3"><?php echo e($payment->receivedBy->name); ?></td>
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
<?php $component->withAttributes([]); ?><?php echo e(__('ui.sales.no_payments')); ?> <?php echo $__env->renderComponent(); ?>
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

            <?php if($sale->installments->isNotEmpty()): ?>
                <h2 class="mb-2 text-base font-semibold text-gray-800"><?php echo e(__('ui.sales.installment_schedule')); ?></h2>
                <div class="mb-6 overflow-hidden rounded-lg bg-white shadow-soft">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 text-left text-gray-500">
                                <th class="px-4 py-3 font-medium"><?php echo e(__('ui.sales.sequence')); ?></th>
                                <th class="px-4 py-3 font-medium"><?php echo e(__('ui.sales.due_date')); ?></th>
                                <th class="px-4 py-3 font-medium"><?php echo e(__('ui.sales.amount')); ?></th>
                                <th class="px-4 py-3 font-medium"><?php echo e(__('ui.sales.paid')); ?></th>
                                <th class="px-4 py-3 font-medium"><?php echo e(__('ui.sales.remaining')); ?></th>
                                <th class="px-4 py-3 font-medium"><?php echo e(__('ui.sales.status')); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $__currentLoopData = $sale->installments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $installment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <tr class="border-b border-gray-50 last:border-0">
                                    <td class="px-4 py-3"><?php echo e($installment->sequence); ?></td>
                                    <td class="px-4 py-3"><?php echo e($installment->due_date->format('d/m/Y')); ?></td>
                                    <td class="px-4 py-3 tnum"><?php echo e(\App\Support\Money::fromDecimal((string) $installment->amount)->format()); ?></td>
                                    <td class="px-4 py-3 tnum"><?php echo e(\App\Support\Money::fromDecimal((string) $installment->paid_amount)->format()); ?></td>
                                    <td class="px-4 py-3 tnum"><?php echo e($installment->remaining()->format()); ?></td>
                                    <td class="px-4 py-3">
                                        <?php if($installment->effectiveStatus() === 'overdue'): ?>
                                            <span class="rounded bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700"><?php echo e(__('ui.sales.inst_overdue')); ?></span>
                                        <?php elseif($installment->status === 'paid'): ?>
                                            <span class="rounded bg-lentera-50 px-2 py-0.5 text-xs font-medium text-lentera-800"><?php echo e(__('ui.sales.inst_paid')); ?></span>
                                        <?php elseif($installment->status === 'partial'): ?>
                                            <span class="rounded bg-gold/10 px-2 py-0.5 text-xs font-medium text-gold-dark"><?php echo e(__('ui.sales.inst_partial')); ?></span>
                                        <?php else: ?>
                                            <span class="rounded bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600"><?php echo e(__('ui.sales.inst_open')); ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if($sale->returns->isNotEmpty()): ?>
                <h2 class="mb-2 text-base font-semibold text-gray-800"><?php echo e(__('ui.sales.returns')); ?></h2>
                <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 text-left text-gray-500">
                                <th class="px-4 py-3 font-medium"><?php echo e(__('ui.sales.number')); ?></th>
                                <th class="px-4 py-3 font-medium"><?php echo e(__('ui.sales.date')); ?></th>
                                <th class="px-4 py-3 font-medium"><?php echo e(__('ui.sales.return_reason')); ?></th>
                                <th class="px-4 py-3 font-medium"><?php echo e(__('ui.sales.refund_method')); ?></th>
                                <th class="px-4 py-3 font-medium"><?php echo e(__('ui.sales.total')); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $__currentLoopData = $sale->returns; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $return): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <tr class="border-b border-gray-50 last:border-0">
                                    <td class="px-4 py-3 font-medium text-gray-800"><?php echo e($return->number); ?></td>
                                    <td class="px-4 py-3"><?php echo e($return->created_at->format('d/m/Y')); ?></td>
                                    <td class="px-4 py-3"><?php echo e($return->reason); ?></td>
                                    <td class="px-4 py-3"><?php echo e(__('ui.sales.refund_methods.'.$return->refund_method)); ?></td>
                                    <td class="px-4 py-3 tnum"><?php echo e(\App\Support\Money::fromDecimal((string) $return->total_amount)->format()); ?></td>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </tbody>
                    </table>
                </div>
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
<?php /**PATH /home/hatch/workspace/lentera/resources/views/sales/show.blade.php ENDPATH**/ ?>