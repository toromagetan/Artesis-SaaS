<?php if (isset($component)) { $__componentOriginal4320b699734ebae421cdb2677fa82f16 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4320b699734ebae421cdb2677fa82f16 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.print-layout','data' => ['title' => __('ui.print_doc.user_profile'),'storeName' => $storeName,'printedBy' => $printedBy]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('print-layout'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('ui.print_doc.user_profile')),'storeName' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($storeName),'printedBy' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($printedBy)]); ?>
    <div class="doc-title"><?php echo e(__('ui.print_doc.user_profile')); ?></div>
    <dl>
        <dt><?php echo e(__('ui.users.name')); ?></dt>
        <dd><?php echo e($user->name); ?></dd>
        <dt><?php echo e(__('ui.users.username')); ?></dt>
        <dd><?php echo e($user->username); ?></dd>
        <dt><?php echo e(__('ui.users.email')); ?></dt>
        <dd><?php echo e($user->email ?: '—'); ?></dd>
        <dt><?php echo e(__('ui.users.phone')); ?></dt>
        <dd><?php echo e($user->phone ?: '—'); ?></dd>
        <dt><?php echo e(__('ui.users.roles')); ?></dt>
        <dd><?php echo e($user->roles->pluck('label')->join(', ') ?: '—'); ?></dd>
        <dt><?php echo e(__('ui.users.branches')); ?></dt>
        <dd><?php echo e($user->hasGlobalAccess() ? __('ui.users.all_branches') : ($user->branches->pluck('name')->join(', ') ?: '—')); ?></dd>
        <dt><?php echo e(__('ui.users.status')); ?></dt>
        <dd><?php echo e($user->is_active ? __('ui.active') : __('ui.inactive')); ?></dd>
        <dt><?php echo e(__('ui.users.member_since')); ?></dt>
        <dd class="tnum"><?php echo e($user->created_at?->format('d/m/Y')); ?></dd>
    </dl>
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
<?php /**PATH /home/hatch/workspace/lentera/resources/views/identity/users/print-profile.blade.php ENDPATH**/ ?>