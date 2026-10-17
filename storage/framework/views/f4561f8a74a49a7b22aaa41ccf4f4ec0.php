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
        <h1 class="text-xl font-semibold leading-tight text-lentera-950"><?php echo e(__('ui.precal.title')); ?> — <?php echo e($prescription->customer->name); ?></h1>
     <?php $__env->endSlot(); ?>

    <div class="py-8">
        <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
            <div class="mb-4 flex items-center justify-between">
                <a href="<?php echo e(route('prescriptions.show', $prescription)); ?>" class="text-sm text-gray-600 underline"><?php echo e(__('ui.prescriptions.detail')); ?></a>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.lentera.menu-item','data' => ['href' => route('prescriptions.precal-print', $prescription),'target' => '_blank']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('lentera.menu-item'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('prescriptions.precal-print', $prescription)),'target' => '_blank']); ?><?php echo e(__('ui.precal.print')); ?> <?php echo $__env->renderComponent(); ?>
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
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.precal.parameter')); ?></th>
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.prescriptions.eye_right')); ?></th>
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.prescriptions.eye_left')); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__currentLoopData = __('ui.precal.parameters'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 text-gray-500"><?php echo e($label); ?></td>
                                <td class="px-4 py-3 font-medium text-gray-800 tnum">
                                    <?php if($key === 'rx_feasibility'): ?>
                                        <?php echo e(__('ui.precal.'.$precal['od'][$key])); ?>

                                    <?php elseif($key === 'pair_summary'): ?>
                                        <?php echo e(__('ui.precal.'.$precal['od'][$key])); ?>

                                    <?php else: ?>
                                        <?php echo e($precal['od'][$key] ?? '—'); ?>

                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 font-medium text-gray-800 tnum">
                                    <?php if($key === 'rx_feasibility'): ?>
                                        <?php echo e(__('ui.precal.'.$precal['os'][$key])); ?>

                                    <?php elseif($key === 'pair_summary'): ?>
                                        <?php echo e(__('ui.precal.'.$precal['os'][$key])); ?>

                                    <?php else: ?>
                                        <?php echo e($precal['os'][$key] ?? '—'); ?>

                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </tbody>
                </table>
            </div>

            <p class="mt-4 text-xs text-gray-400"><?php echo e(__('ui.precal.estimation_note', ['index' => $precal['assumptions']['lens_index'], 'blank' => $precal['assumptions']['blank_diameter_mm'], 'frame' => $precal['assumptions']['frame_center_distance_mm']])); ?></p>
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
<?php /**PATH /home/hatch/workspace/lentera/resources/views/clinical/prescriptions/precal.blade.php ENDPATH**/ ?>