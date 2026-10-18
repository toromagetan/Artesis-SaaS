<?php if (isset($component)) { $__componentOriginal4320b699734ebae421cdb2677fa82f16 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4320b699734ebae421cdb2677fa82f16 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.print-layout','data' => ['title' => __('ui.prescriptions.detail'),'storeName' => $storeName,'paper' => 'A5 portrait']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('print-layout'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.prescriptions.detail')),'store-name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($storeName),'paper' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute('A5 portrait')]); ?>
    <h1 style="font-size:14pt; margin:0 0 2mm;"><?php echo e(__('ui.prescriptions.detail')); ?></h1>
    <p style="margin:0 0 4mm;"><?php echo e($prescription->customer->name); ?> · <?php echo e(__('ui.prescriptions.examined_at')); ?>: <?php echo e($prescription->examined_at->format('d/m/Y')); ?> · <?php echo e($prescription->branch->name); ?></p>

    <table style="width:100%; border-collapse:collapse;" border="1" cellpadding="4">
        <thead>
            <tr>
                <th></th>
                <th><?php echo e(__('ui.prescriptions.sph')); ?></th>
                <th><?php echo e(__('ui.prescriptions.cyl')); ?></th>
                <th><?php echo e(__('ui.prescriptions.axis')); ?></th>
                <th><?php echo e(__('ui.prescriptions.add')); ?></th>
                <th>PD</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>OD</td>
                <td><?php echo e($prescription->od_sph); ?></td>
                <td><?php echo e($prescription->od_cyl); ?></td>
                <td><?php echo e($prescription->od_axis ?? '—'); ?></td>
                <td><?php echo e($prescription->od_add ?? '—'); ?></td>
                <td><?php echo e($prescription->od_pdr ?? '—'); ?></td>
            </tr>
            <tr>
                <td>OS</td>
                <td><?php echo e($prescription->os_sph); ?></td>
                <td><?php echo e($prescription->os_cyl); ?></td>
                <td><?php echo e($prescription->os_axis ?? '—'); ?></td>
                <td><?php echo e($prescription->os_add ?? '—'); ?></td>
                <td><?php echo e($prescription->os_pdl ?? '—'); ?></td>
            </tr>
        </tbody>
    </table>

    <p style="margin-top:4mm;"><?php echo e(__('ui.prescriptions.pd_total')); ?>: <?php echo e($prescription->pd_total ?? '—'); ?> · <?php echo e(__('ui.prescriptions.prism')); ?>: <?php echo e($prescription->prism ?: '—'); ?> · <?php echo e(__('ui.prescriptions.examiner')); ?>: <?php echo e($prescription->examiner?->name ?: '—'); ?></p>
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
<?php /**PATH /home/hatch/workspace/lentera/resources/views/clinical/prescriptions/print.blade.php ENDPATH**/ ?>