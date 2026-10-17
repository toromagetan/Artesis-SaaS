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
        <h1 class="text-xl font-semibold leading-tight text-lentera-950"><?php echo e(__('ui.products.detail')); ?></h1>
     <?php $__env->endSlot(); ?>

    <div class="py-8">
        <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
            <?php if(session('status')): ?>
                <div class="mb-4 rounded-lg border border-lentera-200 bg-lentera-50 px-4 py-3 text-sm text-lentera-800"><?php echo e(session('status')); ?></div>
            <?php endif; ?>

            <div class="mb-4 flex items-center justify-between">
                <a href="<?php echo e(route('products.index', $product->type)); ?>" class="text-sm text-gray-600 underline"><?php echo e(__('ui.back')); ?></a>
                <?php if(auth()->user()->hasPermission('products.update') || auth()->user()->hasPermission('products.deactivate') || auth()->user()->hasPermission('products.create')): ?>
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
                        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('products.create')): ?>
                            <?php if (isset($component)) { $__componentOriginal462fb62fb1260a0c7276ba80b762793e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal462fb62fb1260a0c7276ba80b762793e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.lentera.menu-item','data' => ['href' => route('products.create', $product->type)]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('lentera.menu-item'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('products.create', $product->type))]); ?><?php echo e(__('ui.products.create')); ?> <?php echo $__env->renderComponent(); ?>
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

            <div class="rounded-lg bg-white p-6 shadow-soft">
                <div class="mb-4 flex items-center gap-3">
                    <h2 class="text-lg font-semibold text-gray-800"><?php echo e($product->name); ?></h2>
                    <span class="inline-flex items-center rounded-full border border-gold/40 bg-gold/10 px-2 py-0.5 text-xs font-semibold text-gold-dark"><?php echo e($product->typeLabel()); ?></span>
                    <?php if (isset($component)) { $__componentOriginal1a10f463ba2873b939123688d39cb7c8 = $component; } ?>
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
<?php endif; ?>
                </div>
                <dl class="grid grid-cols-[160px_1fr] gap-y-3 text-sm">
                    <dt class="text-gray-500"><?php echo e(__('ui.products.sku')); ?></dt>
                    <dd class="font-medium text-gray-800"><?php echo e($product->sku); ?></dd>
                    <dt class="text-gray-500"><?php echo e(__('ui.products.brand')); ?></dt>
                    <dd class="font-medium text-gray-800"><?php echo e($product->brand ?: '—'); ?></dd>
                    <dt class="text-gray-500"><?php echo e(__('ui.products.color')); ?></dt>
                    <dd class="font-medium text-gray-800"><?php echo e($product->color ?: '—'); ?></dd>
                    <dt class="text-gray-500"><?php echo e(__('ui.products.cost_price')); ?></dt>
                    <dd class="font-medium text-gray-800 tnum"><?php echo e(\App\Support\Money::fromDecimal((string) $product->cost_price)->format()); ?></dd>
                    <dt class="text-gray-500"><?php echo e(__('ui.products.sale_price')); ?></dt>
                    <dd class="font-medium text-gray-800 tnum"><?php echo e(\App\Support\Money::fromDecimal((string) $product->sale_price)->format()); ?></dd>
                    <?php $__currentLoopData = \App\Modules\Catalog\Services\ProductService::ATTRIBUTE_KEYS[$product->type]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <dt class="text-gray-500"><?php echo e(__('ui.products.attributes.'.$key)); ?></dt>
                        <dd class="font-medium text-gray-800">
                            <?php if($key === 'expiry_tracked'): ?>
                                <?php echo e($product->attributeValue($key) === '1' ? __('ui.yes') : __('ui.no')); ?>

                            <?php else: ?>
                                <?php echo e($product->attributeValue($key) ?: '—'); ?>

                            <?php endif; ?>
                        </dd>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </dl>
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
<?php /**PATH /home/hatch/workspace/lentera/resources/views/catalog/products/show.blade.php ENDPATH**/ ?>