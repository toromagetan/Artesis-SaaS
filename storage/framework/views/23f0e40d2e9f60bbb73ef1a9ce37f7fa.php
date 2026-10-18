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
        <h1 class="text-xl font-semibold leading-tight text-lentera-950"><?php echo e(__('ui.lab.detail')); ?> — <?php echo e($process->sale->number); ?></h1>
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
                <a href="<?php echo e(route('lab.index')); ?>" class="text-sm text-gray-600 underline"><?php echo e(__('ui.back')); ?></a>
                <?php if (! ($process->isDone())): ?>
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
                        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('lab.assign')): ?>
                            <?php $__currentLoopData = ['edging', 'qc', 'ready']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $next): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <?php if(['waiting' => 'edging', 'edging' => 'qc', 'qc' => 'ready'][$process->status] === $next): ?>
                                    <form method="POST" action="<?php echo e(route('lab.transition', $process)); ?>">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="to" value="<?php echo e($next); ?>">
                                        <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-gray-700 transition duration-150 hover:bg-lentera-50 hover:text-lentera-900"><?php echo e(__('ui.lab.advance')); ?>: <?php echo e(__('ui.lab.statuses.'.$next)); ?></button>
                                    </form>
                                <?php endif; ?>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        <?php endif; ?>
                        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('lab.complete')): ?>
                            <form method="POST" action="<?php echo e(route('lab.complete', $process)); ?>" onsubmit="return confirm('<?php echo e(__('ui.lab.complete')); ?>?')">
                                <?php echo csrf_field(); ?>
                                <button type="submit" class="block w-full px-4 py-2 text-left text-sm font-medium text-lentera-800 transition duration-150 hover:bg-lentera-50"><?php echo e(__('ui.lab.complete')); ?></button>
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

            <div class="mb-6 rounded-lg bg-white p-6 shadow-soft">
                <dl class="grid grid-cols-[180px_1fr] gap-y-3 text-sm">
                    <dt class="text-gray-500"><?php echo e(__('ui.lab.sale_number')); ?></dt>
                    <dd class="font-medium text-gray-800"><a class="text-lentera-800 underline-offset-2 hover:underline" href="<?php echo e(route('sales.show', $process->sale)); ?>"><?php echo e($process->sale->number); ?></a></dd>
                    <dt class="text-gray-500"><?php echo e(__('ui.lab.customer')); ?></dt>
                    <dd class="font-medium text-gray-800"><?php echo e($process->sale->customer->name); ?> · <?php echo e($process->sale->customer->phone); ?></dd>
                    <dt class="text-gray-500"><?php echo e(__('ui.sales.branch')); ?></dt>
                    <dd class="font-medium text-gray-800"><?php echo e($process->sale->branch->name); ?></dd>
                    <dt class="text-gray-500"><?php echo e(__('ui.lab.status')); ?></dt>
                    <dd class="font-medium text-gray-800"><?php echo e(__('ui.lab.statuses.'.$process->status)); ?></dd>
                    <?php if($procurementStage !== null): ?>
                        <dt class="text-gray-500"><?php echo e(__('ui.lab.lens_status')); ?></dt>
                        <dd class="font-medium text-gray-800"><?php echo e(__('ui.lab.stages.'.$procurementStage)); ?></dd>
                    <?php endif; ?>
                    <dt class="text-gray-500"><?php echo e(__('ui.lab.technician')); ?></dt>
                    <dd class="font-medium text-gray-800"><?php echo e($process->technician?->name ?? __('ui.lab.unassigned')); ?></dd>
                    <dt class="text-gray-500"><?php echo e(__('ui.lab.estimate')); ?></dt>
                    <dd class="font-medium text-gray-800 tnum"><?php echo e($process->estimated_days ?? '—'); ?></dd>
                    <dt class="text-gray-500"><?php echo e(__('ui.lab.started_at')); ?></dt>
                    <dd class="font-medium text-gray-800"><?php echo e($process->started_at->format('d/m/Y H:i')); ?></dd>
                    <dt class="text-gray-500"><?php echo e(__('ui.lab.completed_at')); ?></dt>
                    <dd class="font-medium text-gray-800"><?php echo e($process->completed_at?->format('d/m/Y H:i') ?? '—'); ?></dd>
                    <dt class="text-gray-500"><?php echo e(__('ui.lab.duration')); ?></dt>
                    <dd class="font-medium text-gray-800 tnum"><?php echo e($process->durationDays()); ?></dd>
                </dl>
            </div>

            <?php if($process->sale->prescription_snapshot): ?>
                <h2 class="mb-2 text-base font-semibold text-gray-800"><?php echo e(__('ui.sales.prescription')); ?></h2>
                <div class="mb-6 rounded-lg bg-white p-6 shadow-soft">
                    <div class="grid gap-4 md:grid-cols-2">
                        <?php $__currentLoopData = ['od' => 'eye_right', 'os' => 'eye_left']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $side => $labelKey): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <div class="rounded-lg border border-gray-200 p-4">
                                <h3 class="mb-3 text-sm font-semibold text-lentera-900"><?php echo e(__('ui.prescriptions.'.$labelKey)); ?></h3>
                                <dl class="grid grid-cols-2 gap-y-2 text-sm tnum">
                                    <dt class="text-gray-500"><?php echo e(__('ui.prescriptions.sph')); ?></dt>
                                    <dd class="font-medium text-gray-800"><?php echo e($process->sale->prescription_snapshot[$side]['sph']); ?></dd>
                                    <dt class="text-gray-500"><?php echo e(__('ui.prescriptions.cyl')); ?></dt>
                                    <dd class="font-medium text-gray-800"><?php echo e($process->sale->prescription_snapshot[$side]['cyl']); ?></dd>
                                    <dt class="text-gray-500"><?php echo e(__('ui.prescriptions.axis')); ?></dt>
                                    <dd class="font-medium text-gray-800"><?php echo e($process->sale->prescription_snapshot[$side]['axis'] ?? '—'); ?></dd>
                                    <dt class="text-gray-500"><?php echo e(__('ui.prescriptions.add')); ?></dt>
                                    <dd class="font-medium text-gray-800"><?php echo e($process->sale->prescription_snapshot[$side]['add'] ?? '—'); ?></dd>
                                </dl>
                            </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                </div>
            <?php endif; ?>

            
            <h2 class="mb-2 text-base font-semibold text-gray-800"><?php echo e(__('ui.qc.checklist')); ?></h2>
            <div class="mb-6 overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.qc.point')); ?></th>
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.qc.latest')); ?></th>
                            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('lab.qc')): ?>
                                <?php if (! ($process->isDone())): ?>
                                    <th class="px-4 py-3 font-medium"><?php echo e(__('ui.qc.record')); ?></th>
                                <?php endif; ?>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__empty_1 = true; $__currentLoopData = $qcPoints; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $point): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <?php $latest = $qcLatest[$point->id] ?? null; ?>
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3">
                                    <span class="font-medium text-gray-800"><?php echo e($point->name); ?></span>
                                    <?php if($point->is_required): ?>
                                        <span class="ml-1 inline-block rounded-full border border-gold/50 bg-gold/10 px-2 py-0.5 text-[11px] font-semibold text-gold-dark"><?php echo e(__('ui.qc.required')); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3">
                                    <?php if($latest === null): ?>
                                        <span class="inline-block rounded-full border border-gray-200 bg-gray-50 px-2.5 py-0.5 text-[11px] font-semibold text-gray-500"><?php echo e(__('ui.qc.not_checked')); ?></span>
                                    <?php elseif($latest->result === 'pass'): ?>
                                        <span class="inline-block rounded-full border border-lentera-200 bg-lentera-50 px-2.5 py-0.5 text-[11px] font-semibold text-lentera-700"><?php echo e(__('ui.qc.pass')); ?></span>
                                        <span class="ml-1 text-xs text-gray-400"><?php echo e(__('ui.qc.attempt')); ?> <?php echo e($latest->attempt); ?> · <?php echo e($latest->checked_at->format('d/m/Y H:i')); ?></span>
                                    <?php else: ?>
                                        <span class="inline-block rounded-full border border-red-200 bg-red-50 px-2.5 py-0.5 text-[11px] font-semibold text-red-600"><?php echo e(__('ui.qc.fail')); ?></span>
                                        <span class="ml-1 text-xs text-gray-400"><?php echo e(__('ui.qc.attempt')); ?> <?php echo e($latest->attempt); ?> · <?php echo e($latest->checked_at->format('d/m/Y H:i')); ?></span>
                                    <?php endif; ?>
                                </td>
                                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('lab.qc')): ?>
                                    <?php if (! ($process->isDone())): ?>
                                        <td class="px-4 py-3">
                                            <form method="POST" action="<?php echo e(route('lab.qc.record', $process)); ?>" class="flex flex-wrap items-center gap-2">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="point_id" value="<?php echo e($point->id); ?>">
                                                <input type="text" name="notes" maxlength="255" placeholder="<?php echo e(__('ui.qc.notes')); ?>" class="w-44 rounded-lg border-gray-300 text-sm focus:border-lentera-500 focus:ring-lentera-500">
                                                <button type="submit" name="result" value="pass" class="rounded-lg bg-lentera-700 px-3 py-1.5 text-xs font-semibold text-white transition duration-150 hover:bg-lentera-800"><?php echo e(__('ui.qc.record_pass')); ?></button>
                                                <button type="submit" name="result" value="fail" class="rounded-lg border border-red-300 px-3 py-1.5 text-xs font-semibold text-red-600 transition duration-150 hover:bg-red-50"><?php echo e(__('ui.qc.record_fail')); ?></button>
                                            </form>
                                        </td>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <tr>
                                <td colspan="3" class="px-4 py-6"><?php if (isset($component)) { $__componentOriginal1fb10244e18bb2e5d2f72f1e8eb61469 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1fb10244e18bb2e5d2f72f1e8eb61469 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.lentera.empty-state','data' => ['message' => __('ui.qc.empty')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('lentera.empty-state'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['message' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.qc.empty'))]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1fb10244e18bb2e5d2f72f1e8eb61469)): ?>
<?php $attributes = $__attributesOriginal1fb10244e18bb2e5d2f72f1e8eb61469; ?>
<?php unset($__attributesOriginal1fb10244e18bb2e5d2f72f1e8eb61469); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1fb10244e18bb2e5d2f72f1e8eb61469)): ?>
<?php $component = $__componentOriginal1fb10244e18bb2e5d2f72f1e8eb61469; ?>
<?php unset($__componentOriginal1fb10244e18bb2e5d2f72f1e8eb61469); ?>
<?php endif; ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <h2 class="mb-2 text-base font-semibold text-gray-800"><?php echo e(__('ui.qc.history')); ?></h2>
            <div class="mb-6 overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.qc.point')); ?></th>
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.qc.attempt')); ?></th>
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.qc.latest')); ?></th>
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.qc.notes')); ?></th>
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.qc.checked_by')); ?></th>
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.qc.checked_at')); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__empty_1 = true; $__currentLoopData = $qcHistory; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $check): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 text-gray-800"><?php echo e($check->point->name); ?></td>
                                <td class="px-4 py-3 tnum"><?php echo e($check->attempt); ?></td>
                                <td class="px-4 py-3">
                                    <?php if($check->result === 'pass'): ?>
                                        <span class="inline-block rounded-full border border-lentera-200 bg-lentera-50 px-2.5 py-0.5 text-[11px] font-semibold text-lentera-700"><?php echo e(__('ui.qc.pass')); ?></span>
                                    <?php else: ?>
                                        <span class="inline-block rounded-full border border-red-200 bg-red-50 px-2.5 py-0.5 text-[11px] font-semibold text-red-600"><?php echo e(__('ui.qc.fail')); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-gray-600"><?php echo e($check->notes ?? '—'); ?></td>
                                <td class="px-4 py-3 text-gray-600"><?php echo e($check->checker->name); ?></td>
                                <td class="px-4 py-3 text-gray-600"><?php echo e($check->checked_at->format('d/m/Y H:i')); ?></td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <tr>
                                <td colspan="6" class="px-4 py-6"><?php if (isset($component)) { $__componentOriginal1fb10244e18bb2e5d2f72f1e8eb61469 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1fb10244e18bb2e5d2f72f1e8eb61469 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.lentera.empty-state','data' => ['message' => __('ui.qc.history_empty')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('lentera.empty-state'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['message' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.qc.history_empty'))]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1fb10244e18bb2e5d2f72f1e8eb61469)): ?>
<?php $attributes = $__attributesOriginal1fb10244e18bb2e5d2f72f1e8eb61469; ?>
<?php unset($__attributesOriginal1fb10244e18bb2e5d2f72f1e8eb61469); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1fb10244e18bb2e5d2f72f1e8eb61469)): ?>
<?php $component = $__componentOriginal1fb10244e18bb2e5d2f72f1e8eb61469; ?>
<?php unset($__componentOriginal1fb10244e18bb2e5d2f72f1e8eb61469); ?>
<?php endif; ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('lab.assign')): ?>
                <?php if (! ($process->isDone())): ?>
                    <h2 class="mb-2 text-base font-semibold text-gray-800"><?php echo e(__('ui.lab.assign')); ?></h2>
                    <div class="rounded-lg bg-white p-6 shadow-soft">
                        <form method="POST" action="<?php echo e(route('lab.assign', $process)); ?>" class="flex flex-wrap items-end gap-4">
                            <?php echo csrf_field(); ?>
                            <div>
                                <?php if (isset($component)) { $__componentOriginale3da9d84bb64e4bc2eeebaafabfb2581 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale3da9d84bb64e4bc2eeebaafabfb2581 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.input-label','data' => ['for' => 'technician_id','value' => __('ui.lab.technician')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('input-label'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['for' => 'technician_id','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.lab.technician'))]); ?>
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
                                <select id="technician_id" name="technician_id" class="mt-1 block w-64 rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500" required>
                                    <option value="">—</option>
                                    <?php $__currentLoopData = $technicians; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $technician): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <option value="<?php echo e($technician->id); ?>" <?php if($process->technician_id === $technician->id): echo 'selected'; endif; ?>><?php echo e($technician->name); ?></option>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </select>
                            </div>
                            <div>
                                <?php if (isset($component)) { $__componentOriginale3da9d84bb64e4bc2eeebaafabfb2581 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale3da9d84bb64e4bc2eeebaafabfb2581 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.input-label','data' => ['for' => 'estimated_days','value' => __('ui.lab.estimate')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('input-label'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['for' => 'estimated_days','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.lab.estimate'))]); ?>
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
                                <?php if (isset($component)) { $__componentOriginal18c21970322f9e5c938bc954620c12bb = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal18c21970322f9e5c938bc954620c12bb = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.text-input','data' => ['id' => 'estimated_days','name' => 'estimated_days','type' => 'number','min' => '1','max' => '90','class' => 'mt-1 block w-32 tnum','value' => $process->estimated_days]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('text-input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => 'estimated_days','name' => 'estimated_days','type' => 'number','min' => '1','max' => '90','class' => 'mt-1 block w-32 tnum','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($process->estimated_days)]); ?>
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
                            <?php if (isset($component)) { $__componentOriginald411d1792bd6cc877d687758b753742c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald411d1792bd6cc877d687758b753742c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.primary-button','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('primary-button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?><?php echo e(__('ui.lab.assign_save')); ?> <?php echo $__env->renderComponent(); ?>
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
                    </div>
                <?php endif; ?>
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
<?php /**PATH /home/hatch/workspace/lentera/resources/views/lab/show.blade.php ENDPATH**/ ?>