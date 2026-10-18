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
        <h1 class="text-xl font-semibold leading-tight text-lentera-950"><?php echo e(__('ui.ai.title')); ?></h1>
     <?php $__env->endSlot(); ?>

    <div class="py-8">
        <div class="mx-auto max-w-3xl space-y-6 sm:px-6 lg:px-8">
            <div class="rounded-lg bg-white p-6 shadow-soft">
                <form method="POST" action="<?php echo e(route('assistant.ask')); ?>" class="flex gap-3">
                    <?php echo csrf_field(); ?>
                    <?php if (isset($component)) { $__componentOriginal18c21970322f9e5c938bc954620c12bb = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal18c21970322f9e5c938bc954620c12bb = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.text-input','data' => ['name' => 'question','class' => 'block w-full','value' => $question,'required' => true,'maxlength' => '500','placeholder' => ''.e(__('ui.ai.placeholder')).'']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('text-input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'question','class' => 'block w-full','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($question),'required' => true,'maxlength' => '500','placeholder' => ''.e(__('ui.ai.placeholder')).'']); ?>
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
                    <?php if (isset($component)) { $__componentOriginald411d1792bd6cc877d687758b753742c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald411d1792bd6cc877d687758b753742c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.primary-button','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('primary-button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?><?php echo e(__('ui.ai.ask')); ?> <?php echo $__env->renderComponent(); ?>
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
                <p class="mt-3 text-xs text-gray-400"><?php echo e(__('ui.ai.examples')); ?></p>
            </div>

            <?php if($result): ?>
                <div class="rounded-lg bg-white p-6 shadow-soft">
                    <p class="text-sm text-gray-500"><?php echo e($question); ?></p>
                    <?php if($result['status'] === 'ok'): ?>
                        <p class="mt-2 text-2xl font-semibold text-lentera-900"><?php echo e($result['text']); ?></p>
                        <div class="mt-4 flex flex-wrap gap-2 text-xs">
                            <span class="rounded-full bg-lentera-50 px-2.5 py-1 font-medium text-lentera-800 ring-1 ring-inset ring-lentera-600/20"><?php echo e(__('ui.ai.basis_period')); ?>: <?php echo e($result['basis']['from'] ?? ''); ?> – <?php echo e($result['basis']['to'] ?? ''); ?></span>
                            <span class="rounded-full bg-lentera-50 px-2.5 py-1 font-medium text-lentera-800 ring-1 ring-inset ring-lentera-600/20"><?php echo e(__('ui.ai.basis_branches')); ?>: <?php echo e(implode(', ', $result['basis']['branches'] ?? [])); ?></span>
                            <?php if(! empty($result['basis']['source'])): ?>
                                <span class="rounded-full bg-lentera-50 px-2.5 py-1 font-medium text-lentera-800 ring-1 ring-inset ring-lentera-600/20"><?php echo e(__('ui.ai.basis_source')); ?>: <?php echo e($result['basis']['source']); ?></span>
                            <?php endif; ?>
                        </div>
                    <?php elseif($result['status'] === 'ai_not_configured'): ?>
                        <p class="mt-2 rounded-lg border border-gold/50 bg-gold/10 px-4 py-3 text-sm text-gold-dark"><?php echo e(__('ui.ai.not_configured')); ?></p>
                    <?php elseif($result['status'] === 'ai_provider_error'): ?>
                        <p class="mt-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?php echo e(__('ui.ai.provider_error')); ?></p>
                    <?php else: ?>
                        <p class="mt-2 text-sm text-gray-600"><?php echo e(__('ui.ai.unknown')); ?></p>
                    <?php endif; ?>
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
<?php /**PATH /home/hatch/workspace/lentera/resources/views/assistant/index.blade.php ENDPATH**/ ?>