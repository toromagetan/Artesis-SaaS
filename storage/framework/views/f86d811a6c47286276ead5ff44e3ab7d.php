<?php if (isset($component)) { $__componentOriginal4320b699734ebae421cdb2677fa82f16 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4320b699734ebae421cdb2677fa82f16 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.print-layout','data' => ['title' => __('ui.print_doc.user_list'),'storeName' => $storeName,'printedBy' => $printedBy]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('print-layout'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.print_doc.user_list')),'storeName' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($storeName),'printedBy' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($printedBy)]); ?>
    <div class="doc-title"><?php echo e(__('ui.print_doc.user_list')); ?></div>
    <table>
        <thead>
            <tr>
                <th><?php echo e(__('ui.users.name')); ?></th>
                <th><?php echo e(__('ui.users.username')); ?></th>
                <th><?php echo e(__('ui.users.roles')); ?></th>
                <th><?php echo e(__('ui.users.branches')); ?></th>
                <th><?php echo e(__('ui.users.status')); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr>
                    <td><?php echo e($user->name); ?></td>
                    <td><?php echo e($user->username); ?></td>
                    <td><?php echo e($user->roles->pluck('label')->join(', ') ?: '—'); ?></td>
                    <td><?php echo e($user->hasGlobalAccess() ? __('ui.users.all_branches') : ($user->branches->pluck('name')->join(', ') ?: '—')); ?></td>
                    <td><?php echo e($user->is_active ? __('ui.active') : __('ui.inactive')); ?></td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
    </table>
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
<?php /**PATH /home/hatch/workspace/lentera/resources/views/identity/users/print-list.blade.php ENDPATH**/ ?>