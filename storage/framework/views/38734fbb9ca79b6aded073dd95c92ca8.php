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
        <h1 class="text-xl font-semibold leading-tight text-lentera-950"><?php echo e(__('ui.search_page.title')); ?> <?php echo e(__('ui.search_page.for')); ?> "<?php echo e($q); ?>"</h1>
     <?php $__env->endSlot(); ?>

    <div class="py-8">
        <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
            <?php if($users->isEmpty() && $branches->isEmpty() && $products->isEmpty() && $customers->isEmpty() && $sales->isEmpty() && $purchaseRequests->isEmpty() && $purchaseOrders->isEmpty()): ?>
                <div class="rounded-lg bg-white shadow-soft">
                    <?php if (isset($component)) { $__componentOriginal1fb10244e18bb2e5d2f72f1e8eb61469 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1fb10244e18bb2e5d2f72f1e8eb61469 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.lentera.empty-state','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('lentera.empty-state'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?><?php echo e(__('ui.search_page.empty', ['q' => $q])); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1fb10244e18bb2e5d2f72f1e8eb61469)): ?>
<?php $attributes = $__attributesOriginal1fb10244e18bb2e5d2f72f1e8eb61469; ?>
<?php unset($__attributesOriginal1fb10244e18bb2e5d2f72f1e8eb61469); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1fb10244e18bb2e5d2f72f1e8eb61469)): ?>
<?php $component = $__componentOriginal1fb10244e18bb2e5d2f72f1e8eb61469; ?>
<?php unset($__componentOriginal1fb10244e18bb2e5d2f72f1e8eb61469); ?>
<?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if($users->isNotEmpty()): ?>
                <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-500"><?php echo e(__('ui.search_page.users')); ?></h2>
                <div class="mb-6 overflow-hidden rounded-lg bg-white shadow-soft">
                    <?php $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <a href="<?php echo e(route('users.show', $user)); ?>" class="flex items-center justify-between border-b border-gray-100 px-4 py-3 transition duration-150 last:border-0 hover:bg-lentera-50">
                            <span>
                                <span class="block font-medium text-gray-800"><?php echo e($user->name); ?></span>
                                <span class="block text-xs text-gray-400"><?php echo e($user->username); ?><?php if($user->email): ?> · <?php echo e($user->email); ?><?php endif; ?></span>
                            </span>
                            <?php if (isset($component)) { $__componentOriginal1a10f463ba2873b939123688d39cb7c8 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1a10f463ba2873b939123688d39cb7c8 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.lentera.badge','data' => ['active' => $user->is_active]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('lentera.badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($user->is_active)]); ?>
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
                        </a>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            <?php endif; ?>

            <?php if($sales->isNotEmpty()): ?>
                <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-500"><?php echo e(__('ui.search_page.sales')); ?></h2>
                <div class="mb-6 overflow-hidden rounded-lg bg-white shadow-soft">
                    <?php $__currentLoopData = $sales; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sale): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <a href="<?php echo e(route('sales.show', $sale)); ?>" class="flex items-center justify-between border-b border-gray-100 px-4 py-3 transition duration-150 last:border-0 hover:bg-lentera-50">
                            <span>
                                <span class="block font-medium text-gray-800"><?php echo e($sale->number); ?></span>
                                <span class="block text-xs text-gray-400"><?php echo e($sale->customer->name); ?> · <?php echo e($sale->sale_date->format('d/m/Y')); ?></span>
                            </span>
                            <span class="tnum text-sm text-gray-600"><?php echo e(\App\Support\Money::fromDecimal((string) $sale->total_amount)->format()); ?></span>
                        </a>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            <?php endif; ?>

            <?php if($purchaseRequests->isNotEmpty()): ?>
                <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-500"><?php echo e(__('ui.search_page.purchase_requests')); ?></h2>
                <div class="mb-6 overflow-hidden rounded-lg bg-white shadow-soft">
                    <?php $__currentLoopData = $purchaseRequests; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $pr): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <a href="<?php echo e(route('pr.show', $pr)); ?>" class="flex items-center justify-between border-b border-gray-100 px-4 py-3 transition duration-150 last:border-0 hover:bg-lentera-50">
                            <span>
                                <span class="block font-medium text-gray-800"><?php echo e($pr->number); ?></span>
                                <span class="block text-xs text-gray-400"><?php echo e($pr->branch->name); ?> · <?php echo e(__('ui.purchasing.pr_statuses.'.$pr->status)); ?></span>
                            </span>
                        </a>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            <?php endif; ?>

            <?php if($purchaseOrders->isNotEmpty()): ?>
                <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-500"><?php echo e(__('ui.search_page.purchase_orders')); ?></h2>
                <div class="mb-6 overflow-hidden rounded-lg bg-white shadow-soft">
                    <?php $__currentLoopData = $purchaseOrders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $po): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <a href="<?php echo e(route('po.show', $po)); ?>" class="flex items-center justify-between border-b border-gray-100 px-4 py-3 transition duration-150 last:border-0 hover:bg-lentera-50">
                            <span>
                                <span class="block font-medium text-gray-800"><?php echo e($po->number); ?></span>
                                <span class="block text-xs text-gray-400"><?php echo e($po->supplier->name); ?> · <?php echo e($po->branch->name); ?></span>
                            </span>
                            <span class="tnum text-sm text-gray-600"><?php echo e(\App\Support\Money::fromDecimal((string) $po->total_amount)->format()); ?></span>
                        </a>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            <?php endif; ?>

            <?php if($customers->isNotEmpty()): ?>
                <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-500"><?php echo e(__('ui.search_page.customers')); ?></h2>
                <div class="mb-6 overflow-hidden rounded-lg bg-white shadow-soft">
                    <?php $__currentLoopData = $customers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $customer): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <a href="<?php echo e(route('customers.show', $customer)); ?>" class="flex items-center justify-between border-b border-gray-100 px-4 py-3 transition duration-150 last:border-0 hover:bg-lentera-50">
                            <span>
                                <span class="block font-medium text-gray-800"><?php echo e($customer->name); ?></span>
                                <span class="block text-xs text-gray-400"><?php echo e($customer->phone); ?></span>
                            </span>
                            <?php if (isset($component)) { $__componentOriginal1a10f463ba2873b939123688d39cb7c8 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1a10f463ba2873b939123688d39cb7c8 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.lentera.badge','data' => ['active' => $customer->is_active]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('lentera.badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($customer->is_active)]); ?>
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
                        </a>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            <?php endif; ?>

            <?php if($products->isNotEmpty()): ?>
                <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-500"><?php echo e(__('ui.search_page.products')); ?></h2>
                <div class="mb-6 overflow-hidden rounded-lg bg-white shadow-soft">
                    <?php $__currentLoopData = $products; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $product): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <a href="<?php echo e(route('products.show', $product)); ?>" class="flex items-center justify-between border-b border-gray-100 px-4 py-3 transition duration-150 last:border-0 hover:bg-lentera-50">
                            <span>
                                <span class="block font-medium text-gray-800"><?php echo e($product->name); ?></span>
                                <span class="block text-xs text-gray-400"><?php echo e($product->sku); ?> · <?php echo e($product->typeLabel()); ?></span>
                            </span>
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
                        </a>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            <?php endif; ?>

            <?php if($branches->isNotEmpty()): ?>
                <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-500"><?php echo e(__('ui.search_page.branches')); ?></h2>
                <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                    <?php $__currentLoopData = $branches; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $branch): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <a href="<?php echo e(route('branches.show', $branch)); ?>" class="flex items-center justify-between border-b border-gray-100 px-4 py-3 transition duration-150 last:border-0 hover:bg-lentera-50">
                            <span>
                                <span class="block font-medium text-gray-800"><?php echo e($branch->name); ?></span>
                                <span class="block text-xs text-gray-400"><?php echo e($branch->phone ?: '—'); ?></span>
                            </span>
                            <?php if (isset($component)) { $__componentOriginal1a10f463ba2873b939123688d39cb7c8 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1a10f463ba2873b939123688d39cb7c8 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.lentera.badge','data' => ['active' => $branch->is_active]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('lentera.badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($branch->is_active)]); ?>
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
                        </a>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
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
<?php /**PATH /home/hatch/workspace/lentera/resources/views/search/index.blade.php ENDPATH**/ ?>