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
        <h1 class="text-xl font-semibold leading-tight text-lentera-950"><?php echo e(__('ui.accounting.account_detail')); ?></h1>
     <?php $__env->endSlot(); ?>

    <div class="py-8">
        <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
            <?php if(session('status')): ?>
                <div class="mb-4 rounded-lg border border-lentera-200 bg-lentera-50 px-4 py-3 text-sm text-lentera-800"><?php echo e(session('status')); ?></div>
            <?php endif; ?>

            <div class="mb-4 flex items-center justify-between">
                <a href="<?php echo e(route('accounts.index')); ?>" class="text-sm text-gray-600 underline"><?php echo e(__('ui.back')); ?></a>
                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('accounts.manage')): ?>
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
                        <?php if (isset($component)) { $__componentOriginal462fb62fb1260a0c7276ba80b762793e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal462fb62fb1260a0c7276ba80b762793e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.lentera.menu-item','data' => ['href' => route('accounts.edit', $account)]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('lentera.menu-item'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('accounts.edit', $account))]); ?><?php echo e(__('ui.edit')); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal462fb62fb1260a0c7276ba80b762793e)): ?>
<?php $attributes = $__attributesOriginal462fb62fb1260a0c7276ba80b762793e; ?>
<?php unset($__attributesOriginal462fb62fb1260a0c7276ba80b762793e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal462fb62fb1260a0c7276ba80b762793e)): ?>
<?php $component = $__componentOriginal462fb62fb1260a0c7276ba80b762793e; ?>
<?php unset($__componentOriginal462fb62fb1260a0c7276ba80b762793e); ?>
<?php endif; ?>
                        <div class="my-1 border-t border-gray-100"></div>
                        <form method="POST" action="<?php echo e(route('accounts.toggle-active', $account)); ?>">
                            <?php echo csrf_field(); ?>
                            <button type="submit" class="block w-full px-4 py-2 text-left text-sm transition duration-150 hover:bg-lentera-50 <?php echo e($account->is_active ? 'text-red-600' : 'text-lentera-700'); ?>"><?php echo e($account->is_active ? __('ui.deactivate') : __('ui.activate')); ?></button>
                        </form>
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
                <dl class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <dt class="text-sm text-gray-500"><?php echo e(__('ui.accounting.code')); ?></dt>
                        <dd class="font-medium text-gray-800 tnum"><?php echo e($account->code); ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500"><?php echo e(__('ui.accounting.name')); ?></dt>
                        <dd class="font-medium text-gray-800"><?php echo e($account->name); ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500"><?php echo e(__('ui.accounting.group')); ?></dt>
                        <dd class="font-medium text-gray-800"><?php echo e(__('ui.accounting.groups.'.$account->group)); ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500"><?php echo e(__('ui.accounting.parent')); ?></dt>
                        <dd class="font-medium text-gray-800"><?php echo e($account->parent ? $account->parent->code.' — '.$account->parent->name : '—'); ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500"><?php echo e(__('ui.accounting.status')); ?></dt>
                        <dd><?php if (isset($component)) { $__componentOriginal1a10f463ba2873b939123688d39cb7c8 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1a10f463ba2873b939123688d39cb7c8 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.lentera.badge','data' => ['active' => $account->is_active]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('lentera.badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['active' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($account->is_active)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1a10f463ba2873b939123688d39cb7c8)): ?>
<?php $attributes = $__attributesOriginal1a10f463ba2873b939123688d39cb7c8; ?>
<?php unset($__attributesOriginal1a10f463ba2873b939123688d39cb7c8); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1a10f463ba2873b939123688d39cb7c8)): ?>
<?php $component = $__componentOriginal1a10f463ba2873b939123688d39cb7c8; ?>
<?php unset($__componentOriginal1a10f463ba2873b939123688d39cb7c8); ?>
<?php endif; ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500"><?php echo e(__('ui.accounting.line_count')); ?></dt>
                        <dd class="font-medium text-gray-800 tnum"><?php echo e($totals->line_count); ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500"><?php echo e(__('ui.accounting.total_debit')); ?></dt>
                        <dd class="font-medium text-gray-800 tnum"><?php echo e(\App\Support\Money::fromDecimal((string) $totals->total_debit)->format()); ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500"><?php echo e(__('ui.accounting.total_credit')); ?></dt>
                        <dd class="font-medium text-gray-800 tnum"><?php echo e(\App\Support\Money::fromDecimal((string) $totals->total_credit)->format()); ?></dd>
                    </div>
                </dl>

                <?php if($account->children->isNotEmpty()): ?>
                    <h2 class="mt-6 text-sm font-semibold text-gray-700"><?php echo e(__('ui.accounting.children')); ?></h2>
                    <ul class="mt-2 divide-y divide-gray-50 text-sm">
                        <?php $__currentLoopData = $account->children; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $child): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <li class="py-2">
                                <a href="<?php echo e(route('accounts.show', $child)); ?>" class="text-lentera-800 underline-offset-2 hover:underline"><span class="tnum"><?php echo e($child->code); ?></span> — <?php echo e($child->name); ?></a>
                            </li>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </ul>
                <?php endif; ?>

                <h2 class="mt-6 text-sm font-semibold text-gray-700"><?php echo e(__('ui.accounting.recent_lines')); ?></h2>
                <table class="mt-2 min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-2 py-2 font-medium"><?php echo e(__('ui.accounting.date')); ?></th>
                            <th class="px-2 py-2 font-medium"><?php echo e(__('ui.accounting.number')); ?></th>
                            <th class="px-2 py-2 text-right font-medium"><?php echo e(__('ui.accounting.debit')); ?></th>
                            <th class="px-2 py-2 text-right font-medium"><?php echo e(__('ui.accounting.credit')); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__empty_1 = true; $__currentLoopData = $recentLines; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $line): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-2 py-2"><?php echo e($line->journal->journal_date->format('d/m/Y')); ?></td>
                                <td class="px-2 py-2">
                                    <a href="<?php echo e(route('journals.show', $line->journal)); ?>" class="text-lentera-800 underline-offset-2 hover:underline tnum"><?php echo e($line->journal->number); ?></a>
                                </td>
                                <td class="px-2 py-2 text-right tnum"><?php echo e(\App\Support\Money::fromDecimal((string) $line->debit)->format()); ?></td>
                                <td class="px-2 py-2 text-right tnum"><?php echo e(\App\Support\Money::fromDecimal((string) $line->credit)->format()); ?></td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <tr>
                                <td colspan="4" class="px-2 py-4 text-gray-500"><?php echo e(__('ui.accounting.no_lines')); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
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
<?php /**PATH /home/hatch/workspace/lentera/resources/views/accounting/accounts/show.blade.php ENDPATH**/ ?>