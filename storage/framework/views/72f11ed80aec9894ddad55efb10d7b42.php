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
        <h1 class="text-xl font-semibold leading-tight text-lentera-950"><?php echo e(__('ui.insurance_claims.detail')); ?> — <?php echo e($usage->sale->number); ?></h1>
     <?php $__env->endSlot(); ?>

    <div class="py-8">
        <div class="mx-auto max-w-5xl space-y-4 sm:px-6 lg:px-8">
            <?php if(session('status')): ?>
                <p class="rounded-md bg-lentera-100 px-4 py-2 text-sm text-lentera-900" role="status"><?php echo e(session('status')); ?></p>
            <?php endif; ?>
            <p><a href="<?php echo e(route('insurance-claims.index')); ?>" class="text-sm text-lentera-800 underline"><?php echo e(__('ui.insurance_claims.back')); ?></a></p>

            <section class="rounded-lg bg-white p-6 shadow-soft">
                <dl class="grid gap-3 text-sm sm:grid-cols-2">
                    <div><dt class="text-gray-500"><?php echo e(__('ui.insurance_claims.sale')); ?></dt><dd class="font-medium"><a href="<?php echo e(route('sales.show', $usage->sale)); ?>" class="text-lentera-800 underline"><?php echo e($usage->sale->number); ?></a></dd></div>
                    <div><dt class="text-gray-500"><?php echo e(__('ui.insurance_claims.insurance')); ?></dt><dd class="font-medium"><?php echo e($usage->insurance->name); ?></dd></div>
                    <div><dt class="text-gray-500"><?php echo e(__('ui.insurance_claims.customer')); ?></dt><dd class="font-medium"><?php echo e($usage->customer->name); ?></dd></div>
                    <div><dt class="text-gray-500"><?php echo e(__('ui.insurance_claims.branch')); ?></dt><dd class="font-medium"><?php echo e($usage->branch->name); ?></dd></div>
                    <div><dt class="text-gray-500"><?php echo e(__('ui.insurance_claims.transaction')); ?></dt><dd class="font-medium tnum"><?php echo e(\App\Support\Money::fromDecimal((string) $usage->transaction_amount)->format()); ?></dd></div>
                    <div><dt class="text-gray-500"><?php echo e(__('ui.insurance_claims.claim')); ?></dt><dd class="font-medium tnum"><?php echo e(\App\Support\Money::fromDecimal((string) $usage->insurance_claim_amount)->format()); ?></dd></div>
                    <div><dt class="text-gray-500"><?php echo e(__('ui.insurance_claims.settled')); ?></dt><dd class="font-medium tnum"><?php echo e(\App\Support\Money::fromDecimal($usage->settledAmount())->format()); ?></dd></div>
                    <div><dt class="text-gray-500"><?php echo e(__('ui.insurance_claims.remaining')); ?></dt><dd class="font-medium tnum"><?php echo e(\App\Support\Money::fromDecimal($usage->remainingAmount())->format()); ?></dd></div>
                </dl>
            </section>

            <section class="overflow-hidden rounded-lg bg-white shadow-soft">
                <h2 class="border-b border-gray-100 px-4 py-3 font-semibold text-gray-800"><?php echo e(__('ui.insurance_claims.receipts')); ?></h2>
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.insurance_claims.received_at')); ?></th>
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.sales.method')); ?></th>
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.insurance_claims.received_by')); ?></th>
                            <th class="px-4 py-3 text-right font-medium"><?php echo e(__('ui.insurance_claims.amount')); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__empty_1 = true; $__currentLoopData = $usage->receipts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $receipt): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 tnum"><?php echo e($receipt->received_at->format('d/m/Y H:i')); ?></td>
                                <td class="px-4 py-3"><?php echo e(__('ui.sales.methods.'.$receipt->method)); ?></td>
                                <td class="px-4 py-3"><?php echo e($receipt->receiver->name); ?></td>
                                <td class="px-4 py-3 text-right tnum"><?php echo e(\App\Support\Money::fromDecimal((string) $receipt->amount)->format()); ?></td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <tr><td colspan="4"><?php if (isset($component)) { $__componentOriginal1fb10244e18bb2e5d2f72f1e8eb61469 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1fb10244e18bb2e5d2f72f1e8eb61469 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.lentera.empty-state','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('lentera.empty-state'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?><?php echo e(__('ui.insurance_claims.no_receipts')); ?> <?php echo $__env->renderComponent(); ?>
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

            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('sales.pay')): ?>
                <?php if($usage->remainingAmount() !== '0.00'): ?>
                    <section class="rounded-lg bg-white p-6 shadow-soft">
                        <h2 class="mb-3 font-semibold text-gray-800"><?php echo e(__('ui.insurance_claims.receive_title')); ?></h2>
                        <form method="POST" action="<?php echo e(route('insurance-claims.receive', $usage)); ?>" class="flex flex-wrap items-end gap-3">
                            <?php echo csrf_field(); ?>
                            <label class="text-sm">
                                <span class="mb-1 block text-gray-500"><?php echo e(__('ui.insurance_claims.amount')); ?></span>
                                <input type="number" name="amount" min="0" step="0.01" required class="rounded-md border-gray-300 text-sm">
                            </label>
                            <label class="text-sm">
                                <span class="mb-1 block text-gray-500"><?php echo e(__('ui.sales.method')); ?></span>
                                <select name="method" class="rounded-md border-gray-300 text-sm">
                                    <?php $__currentLoopData = \App\Modules\Sales\Services\InsuranceClaimService::METHODS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $method): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <option value="<?php echo e($method); ?>"><?php echo e(__('ui.sales.methods.'.$method)); ?></option>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </select>
                            </label>
                            <button type="submit" class="rounded-md bg-lentera-700 px-4 py-2 text-sm font-medium text-white transition duration-150 hover:bg-lentera-800"><?php echo e(__('ui.insurance_claims.receive')); ?></button>
                        </form>
                        <?php if($errors->any()): ?>
                            <ul class="mt-3 text-sm text-red-600">
                                <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($error); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </ul>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>
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
<?php /**PATH /home/hatch/workspace/lentera/resources/views/sales/insurance-claims/show.blade.php ENDPATH**/ ?>