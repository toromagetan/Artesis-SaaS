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
        <h1 class="text-xl font-semibold leading-tight text-lentera-950"><?php echo e(__('ui.incentives.title')); ?></h1>
     <?php $__env->endSlot(); ?>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-4 sm:px-6 lg:px-8">
            <?php if(session('status')): ?>
                <p class="rounded-md bg-lentera-100 px-4 py-2 text-sm text-lentera-900" role="status"><?php echo e(session('status')); ?></p>
            <?php endif; ?>
            <?php if($errors->any()): ?>
                <ul class="rounded-md bg-red-50 px-4 py-2 text-sm text-red-700">
                    <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($error); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </ul>
            <?php endif; ?>

            <section class="rounded-lg bg-white p-6 shadow-soft">
                <h2 class="mb-3 font-semibold text-gray-800"><?php echo e(__('ui.incentives.generate_title')); ?></h2>
                <form method="POST" action="<?php echo e(route('incentives.generate')); ?>" class="flex flex-wrap items-end gap-3">
                    <?php echo csrf_field(); ?>
                    <label class="text-sm">
                        <span class="mb-1 block text-gray-500"><?php echo e(__('ui.incentives.branch')); ?></span>
                        <select name="branch_id" class="rounded-md border-gray-300 text-sm">
                            <?php $__currentLoopData = $branches; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $b): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($b->id); ?>"><?php echo e($b->name); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </label>
                    <label class="text-sm">
                        <span class="mb-1 block text-gray-500"><?php echo e(__('ui.incentives.month')); ?></span>
                        <input type="month" name="month" required class="rounded-md border-gray-300 text-sm">
                    </label>
                    <label class="text-sm">
                        <span class="mb-1 block text-gray-500"><?php echo e(__('ui.incentives.recipient')); ?></span>
                        <select name="user_id" class="rounded-md border-gray-300 text-sm">
                            <option value=""><?php echo e(__('ui.incentives.recipient_all')); ?></option>
                            <?php $__currentLoopData = $recipients; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $u): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($u->id); ?>"><?php echo e($u->name); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </label>
                    <button type="submit" class="rounded-md bg-lentera-700 px-4 py-2 text-sm font-medium text-white transition duration-150 hover:bg-lentera-800"><?php echo e(__('ui.incentives.generate')); ?></button>
                </form>
            </section>

            <section class="overflow-hidden rounded-lg bg-white shadow-soft">
                <h2 class="border-b border-gray-100 px-4 py-3 font-semibold text-gray-800"><?php echo e(__('ui.incentives.periods')); ?></h2>
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.incentives.period')); ?></th>
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.incentives.recipient')); ?></th>
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.incentives.branch')); ?></th>
                            <th class="px-4 py-3 text-right font-medium"><?php echo e(__('ui.incentives.transaction_total')); ?></th>
                            <th class="px-4 py-3 text-right font-medium"><?php echo e(__('ui.incentives.total')); ?></th>
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.incentives.status')); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__empty_1 = true; $__currentLoopData = $periods; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $period): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <tr class="border-b border-gray-50 last:border-0 hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <a href="<?php echo e(route('incentives.show', $period)); ?>" class="font-medium text-lentera-800 underline"><?php echo e($period->period_start->format('d/m/Y')); ?> – <?php echo e($period->period_end->format('d/m/Y')); ?></a>
                                </td>
                                <td class="px-4 py-3"><?php echo e($period->recipient?->name ?? __('ui.incentives.recipient_all')); ?></td>
                                <td class="px-4 py-3"><?php echo e($period->branch?->name ?? '—'); ?></td>
                                <td class="px-4 py-3 text-right tnum"><?php echo e(\App\Support\Money::fromDecimal((string) $period->transaction_total)->format()); ?></td>
                                <td class="px-4 py-3 text-right font-medium tnum"><?php echo e($period->netTotal()->format()); ?></td>
                                <td class="px-4 py-3"><?php echo e(__('ui.incentives.status_'.$period->status)); ?></td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <tr><td colspan="6"><?php if (isset($component)) { $__componentOriginal1fb10244e18bb2e5d2f72f1e8eb61469 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1fb10244e18bb2e5d2f72f1e8eb61469 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.lentera.empty-state','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('lentera.empty-state'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?><?php echo e(__('ui.incentives.empty')); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1fb10244e18bb2e5d2f72f1e8eb61469)): ?>
<?php $attributes = $__attributesOriginal1fb10244e18bb2e5d2f72f1e8eb61469; ?>
<?php unset($__attributesOriginal1fb10244e18bb2e5d2f72f1e8eb61469); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1fb10244e18bb2e5d2f72f1e8eb61469)): ?>
<?php $component = $__componentOriginal1fb10244e18bb2e5d2f72f1e8eb61469; ?>
<?php unset($__componentOriginal1fb10244e18bb2e5d2f72f1e8eb61469); ?>
<?php endif; ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <div class="border-t border-gray-50 px-4 py-3"><?php echo e($periods->links()); ?></div>
            </section>

            <section class="rounded-lg bg-white p-6 shadow-soft">
                <h2 class="mb-3 font-semibold text-gray-800"><?php echo e(__('ui.incentives.rules')); ?></h2>
                <form method="POST" action="<?php echo e(route('incentives.rules.store')); ?>" class="mb-4 flex flex-wrap items-end gap-3">
                    <?php echo csrf_field(); ?>
                    <label class="text-sm">
                        <span class="mb-1 block text-gray-500"><?php echo e(__('ui.incentives.rule_name')); ?></span>
                        <input type="text" name="name" required class="rounded-md border-gray-300 text-sm">
                    </label>
                    <label class="text-sm">
                        <span class="mb-1 block text-gray-500"><?php echo e(__('ui.incentives.role')); ?></span>
                        <select name="recipient_role" class="rounded-md border-gray-300 text-sm">
                            <?php $__currentLoopData = $roles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $role): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($role); ?>"><?php echo e($role); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </label>
                    <label class="text-sm">
                        <span class="mb-1 block text-gray-500"><?php echo e(__('ui.incentives.product')); ?></span>
                        <select name="product_id" class="rounded-md border-gray-300 text-sm">
                            <option value=""><?php echo e(__('ui.incentives.product_all')); ?></option>
                            <?php $__currentLoopData = $products; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($p->id); ?>"><?php echo e($p->sku); ?> — <?php echo e($p->name); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </label>
                    <label class="text-sm">
                        <span class="mb-1 block text-gray-500"><?php echo e(__('ui.incentives.calc')); ?></span>
                        <select name="calculation_type" class="rounded-md border-gray-300 text-sm">
                            <option value="percent"><?php echo e(__('ui.incentives.percent')); ?></option>
                            <option value="flat"><?php echo e(__('ui.incentives.flat')); ?></option>
                        </select>
                    </label>
                    <label class="text-sm">
                        <span class="mb-1 block text-gray-500"><?php echo e(__('ui.incentives.value')); ?></span>
                        <input type="number" name="value" min="0" step="0.01" required class="w-28 rounded-md border-gray-300 text-sm">
                    </label>
                    <label class="text-sm">
                        <span class="mb-1 block text-gray-500"><?php echo e(__('ui.incentives.effective_from')); ?></span>
                        <input type="date" name="effective_from" required class="rounded-md border-gray-300 text-sm">
                    </label>
                    <label class="text-sm">
                        <span class="mb-1 block text-gray-500"><?php echo e(__('ui.incentives.effective_to')); ?></span>
                        <input type="date" name="effective_to" class="rounded-md border-gray-300 text-sm">
                    </label>
                    <button type="submit" class="rounded-md bg-lentera-700 px-4 py-2 text-sm font-medium text-white transition duration-150 hover:bg-lentera-800"><?php echo e(__('ui.incentives.add_rule')); ?></button>
                </form>
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.incentives.rule_name')); ?></th>
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.incentives.role')); ?></th>
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.incentives.product')); ?></th>
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.incentives.calc')); ?></th>
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.incentives.effective')); ?></th>
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.incentives.active')); ?></th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__empty_1 = true; $__currentLoopData = $rules; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $rule): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 font-medium"><?php echo e($rule->name); ?></td>
                                <td class="px-4 py-3"><?php echo e($rule->recipient_role); ?></td>
                                <td class="px-4 py-3"><?php echo e($rule->product?->sku ?? __('ui.incentives.product_all')); ?></td>
                                <td class="px-4 py-3"><?php echo e($rule->calculation_type === 'percent' ? $rule->value.'%' : \App\Support\Money::fromDecimal((string) $rule->value)->format()); ?></td>
                                <td class="px-4 py-3 tnum"><?php echo e($rule->effective_from->format('d/m/Y')); ?><?php echo e($rule->effective_to ? ' – '.$rule->effective_to->format('d/m/Y') : ''); ?></td>
                                <td class="px-4 py-3">
                                    <?php if($rule->is_active): ?>
                                        <?php if (isset($component)) { $__componentOriginal1a10f463ba2873b939123688d39cb7c8 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1a10f463ba2873b939123688d39cb7c8 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.lentera.badge','data' => ['active' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('lentera.badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['active' => true]); ?>
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
                                    <?php else: ?>
                                        <?php if (isset($component)) { $__componentOriginal1a10f463ba2873b939123688d39cb7c8 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1a10f463ba2873b939123688d39cb7c8 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.lentera.badge','data' => ['active' => false]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('lentera.badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(false)]); ?>
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
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <form method="POST" action="<?php echo e(route('incentives.rules.toggle', $rule)); ?>">
                                        <?php echo csrf_field(); ?>
                                        <button type="submit" class="text-sm text-lentera-800 underline"><?php echo e($rule->is_active ? __('ui.incentives.deactivate') : __('ui.incentives.activate')); ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <tr><td colspan="7"><?php if (isset($component)) { $__componentOriginal1fb10244e18bb2e5d2f72f1e8eb61469 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1fb10244e18bb2e5d2f72f1e8eb61469 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.lentera.empty-state','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('lentera.empty-state'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?><?php echo e(__('ui.incentives.no_rules_yet')); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1fb10244e18bb2e5d2f72f1e8eb61469)): ?>
<?php $attributes = $__attributesOriginal1fb10244e18bb2e5d2f72f1e8eb61469; ?>
<?php unset($__attributesOriginal1fb10244e18bb2e5d2f72f1e8eb61469); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1fb10244e18bb2e5d2f72f1e8eb61469)): ?>
<?php $component = $__componentOriginal1fb10244e18bb2e5d2f72f1e8eb61469; ?>
<?php unset($__componentOriginal1fb10244e18bb2e5d2f72f1e8eb61469); ?>
<?php endif; ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </section>
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
<?php /**PATH /home/hatch/workspace/lentera/resources/views/accounting/incentives/index.blade.php ENDPATH**/ ?>