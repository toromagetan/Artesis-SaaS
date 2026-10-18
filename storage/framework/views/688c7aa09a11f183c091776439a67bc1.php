<?php if (isset($component)) { $__componentOriginal4320b699734ebae421cdb2677fa82f16 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4320b699734ebae421cdb2677fa82f16 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.print-layout','data' => ['title' => __('ui.precal.title'),'storeName' => $storeName,'paper' => 'A5 portrait']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('print-layout'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.precal.title')),'store-name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($storeName),'paper' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute('A5 portrait')]); ?>
    <h1 style="font-size:14pt; margin:0 0 2mm;"><?php echo e(__('ui.precal.title')); ?></h1>
    <p style="margin:0 0 4mm;"><?php echo e($prescription->customer->name); ?> · <?php echo e(__('ui.prescriptions.examined_at')); ?>: <?php echo e($prescription->examined_at->format('d/m/Y')); ?> · <?php echo e($prescription->branch->name); ?></p>

    <table style="width:100%; border-collapse:collapse;" border="1" cellpadding="4">
        <thead>
            <tr>
                <th><?php echo e(__('ui.precal.parameter')); ?></th>
                <th>OD</th>
                <th>OS</th>
            </tr>
        </thead>
        <tbody>
            <?php $__currentLoopData = __('ui.precal.parameters'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr>
                    <td><?php echo e($label); ?></td>
                    <td><?php if($key === 'rx_feasibility' || $key === 'pair_summary'): ?><?php echo e(__('ui.precal.'.$precal['od'][$key])); ?><?php else: ?><?php echo e($precal['od'][$key] ?? '—'); ?><?php endif; ?></td>
                    <td><?php if($key === 'rx_feasibility' || $key === 'pair_summary'): ?><?php echo e(__('ui.precal.'.$precal['os'][$key])); ?><?php else: ?><?php echo e($precal['os'][$key] ?? '—'); ?><?php endif; ?></td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
    </table>

    <p style="margin-top:4mm; font-size:8pt;"><?php echo e(__('ui.precal.estimation_note', ['index' => $precal['assumptions']['lens_index'], 'blank' => $precal['assumptions']['blank_diameter_mm'], 'frame' => $precal['assumptions']['frame_center_distance_mm']])); ?></p>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4320b699734ebae421cdb2677fa82f16)): ?>
<?php $attributes = $__attributesOriginal4320b699734ebae421cdb2677fa82f16; ?>
<?php unset($__attributesOriginal4320b699734ebae421cdb2677fa82f16); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4320b699734ebae421cdb2677fa82f16)): ?>
<?php $component = $__componentOriginal4320b699734ebae421cdb2677fa82f16; ?>
<?php unset($__componentOriginal4320b699734ebae421cdb2677fa82f16); ?>
<?php endif; ?>
<?php /**PATH /home/hatch/workspace/lentera/resources/views/clinical/prescriptions/precal-print.blade.php ENDPATH**/ ?>