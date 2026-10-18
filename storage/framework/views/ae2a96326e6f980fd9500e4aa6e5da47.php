<tr class="border-b border-gray-50 last:border-0">
    <td class="px-4 py-3 font-medium text-gray-800">
        <span class="flex items-center gap-3">
            <?php if($product->imageUrl()): ?>
                <img src="<?php echo e($product->imageUrl()); ?>" alt="" class="h-9 w-9 rounded object-cover">
            <?php endif; ?>
            <a href="<?php echo e(route('products.show', $product)); ?>" class="text-lentera-800 underline-offset-2 hover:underline"><?php echo e($product->name); ?></a>
        </span>
    </td>
    <td class="px-4 py-3"><?php echo e($product->sku); ?></td>
    <td class="px-4 py-3 text-gray-600"><?php echo e($product->detailLabel() ?: '—'); ?></td>
    <td class="px-4 py-3 tnum"><?php echo e(\App\Support\Money::fromDecimal((string) $product->sale_price)->format()); ?></td>
    <?php if($type === 'softlens'): ?>
        <td class="px-4 py-3">
            <?php if(isset($expiries[$product->id])): ?>
                <span class="tnum <?php echo e($expiries[$product->id]['expired'] ? 'font-semibold text-red-600' : ($expiries[$product->id]['soon'] ? 'font-medium text-gold-dark' : '')); ?>"><?php echo e(\Illuminate\Support\Carbon::parse($expiries[$product->id]['date'])->format('d/m/Y')); ?></span>
            <?php else: ?>
                <span class="text-gray-400">—</span>
            <?php endif; ?>
        </td>
    <?php endif; ?>
    <td class="px-4 py-3"><?php if (isset($component)) { $__componentOriginal1a10f463ba2873b939123688d39cb7c8 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1a10f463ba2873b939123688d39cb7c8 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.lentera.badge','data' => ['active' => $product->is_active]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('lentera.badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($product->is_active)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1a10f463ba2873b939123688d39cb7c8)): ?>
<?php $attributes = $__attributesOriginal1a10f463ba2873b939123688d39cb7c8; ?>
<?php unset($__attributesOriginal1a10f463ba2873b939123688d39cb7c8); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1a10f463ba2873b939123688d39cb7c8)): ?>
<?php $component = $__componentOriginal1a10f463ba2873b939123688d39cb7c8; ?>
<?php unset($__componentOriginal1a10f463ba2873b939123688d39cb7c8); ?>
<?php endif; ?></td>
    <td class="px-4 py-3 text-right">
        <?php if (isset($component)) { $__componentOriginal645c48eba03eed14da3653ca82ef58a2 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal645c48eba03eed14da3653ca82ef58a2 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.lentera.row-actions','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('lentera.row-actions'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
            <?php if (isset($component)) { $__componentOriginal462fb62fb1260a0c7276ba80b762793e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal462fb62fb1260a0c7276ba80b762793e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.lentera.menu-item','data' => ['href' => route('products.show', $product)]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('lentera.menu-item'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('products.show', $product))]); ?><?php echo e(__('ui.products.detail')); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal462fb62fb1260a0c7276ba80b762793e)): ?>
<?php $attributes = $__attributesOriginal462fb62fb1260a0c7276ba80b762793e; ?>
<?php unset($__attributesOriginal462fb62fb1260a0c7276ba80b762793e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal462fb62fb1260a0c7276ba80b762793e)): ?>
<?php $component = $__componentOriginal462fb62fb1260a0c7276ba80b762793e; ?>
<?php unset($__componentOriginal462fb62fb1260a0c7276ba80b762793e); ?>
<?php endif; ?>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('products.update')): ?>
                <?php if (isset($component)) { $__componentOriginal462fb62fb1260a0c7276ba80b762793e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal462fb62fb1260a0c7276ba80b762793e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.lentera.menu-item','data' => ['href' => route('products.edit', $product)]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('lentera.menu-item'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('products.edit', $product))]); ?><?php echo e(__('ui.edit')); ?> <?php echo $__env->renderComponent(); ?>
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
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('products.deactivate')): ?>
                <div class="my-1 border-t border-gray-100"></div>
                <form method="POST" action="<?php echo e(route('products.toggle-active', $product)); ?>">
                    <?php echo csrf_field(); ?>
                    <button type="submit" class="block w-full px-4 py-2 text-left text-sm transition duration-150 hover:bg-lentera-50 <?php echo e($product->is_active ? 'text-red-600' : 'text-lentera-700'); ?>"><?php echo e($product->is_active ? __('ui.deactivate') : __('ui.activate')); ?></button>
                </form>
            <?php endif; ?>
         <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal645c48eba03eed14da3653ca82ef58a2)): ?>
<?php $attributes = $__attributesOriginal645c48eba03eed14da3653ca82ef58a2; ?>
<?php unset($__attributesOriginal645c48eba03eed14da3653ca82ef58a2); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal645c48eba03eed14da3653ca82ef58a2)): ?>
<?php $component = $__componentOriginal645c48eba03eed14da3653ca82ef58a2; ?>
<?php unset($__componentOriginal645c48eba03eed14da3653ca82ef58a2); ?>
<?php endif; ?>
    </td>
</tr>
<?php /**PATH /home/hatch/workspace/lentera/resources/views/catalog/products/_variant-row.blade.php ENDPATH**/ ?>