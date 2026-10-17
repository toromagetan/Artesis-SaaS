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
        <h1 class="text-xl font-semibold leading-tight text-lentera-950"><?php echo e($journal->number); ?></h1>
     <?php $__env->endSlot(); ?>

    <div class="py-8">
        <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
            <?php if(session('status')): ?>
                <div class="mb-4 rounded-lg border border-lentera-200 bg-lentera-50 px-4 py-3 text-sm text-lentera-800"><?php echo e(session('status')); ?></div>
            <?php endif; ?>
            <?php if($errors->any()): ?>
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><div><?php echo e($error); ?></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            <?php endif; ?>

            <div class="mb-4 flex items-center justify-between">
                <a href="<?php echo e(route('journals.index')); ?>" class="text-sm text-gray-600 underline"><?php echo e(__('ui.back')); ?></a>
                <?php if($reversal === null && $journal->source_type !== 'reversal'): ?>
                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('journals.reverse')): ?>
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
                            <form method="POST" action="<?php echo e(route('journals.reverse', $journal)); ?>" class="px-4 py-2">
                                <?php echo csrf_field(); ?>
                                <label class="mb-1 block text-xs text-gray-500" for="reason"><?php echo e(__('ui.accounting.reverse_reason')); ?></label>
                                <input id="reason" name="reason" required class="mb-2 block w-56 rounded-md border-gray-300 text-sm shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                                <button type="submit" class="block w-full rounded-md bg-red-50 px-3 py-1.5 text-left text-sm text-red-600 transition duration-150 hover:bg-red-100"><?php echo e(__('ui.accounting.reverse')); ?></button>
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
                <?php endif; ?>
            </div>

            <div class="mb-6 rounded-lg bg-white p-6 shadow-soft">
                <dl class="grid grid-cols-[160px_1fr] gap-y-3 text-sm">
                    <dt class="text-gray-500"><?php echo e(__('ui.accounting.date')); ?></dt>
                    <dd class="font-medium text-gray-800"><?php echo e($journal->journal_date->format('d/m/Y')); ?></dd>
                    <dt class="text-gray-500"><?php echo e(__('ui.accounting.source')); ?></dt>
                    <dd class="font-medium text-gray-800"><?php echo e($journal->source_type); ?><?php if($journal->source_id): ?> #<?php echo e($journal->source_id); ?><?php endif; ?></dd>
                    <dt class="text-gray-500"><?php echo e(__('ui.accounting.description')); ?></dt>
                    <dd class="font-medium text-gray-800"><?php echo e($journal->description); ?></dd>
                    <?php if($journal->reversalOf): ?>
                        <dt class="text-gray-500"><?php echo e(__('ui.accounting.reversal_of')); ?></dt>
                        <dd class="font-medium text-gray-800"><a class="text-lentera-800 underline-offset-2 hover:underline" href="<?php echo e(route('journals.show', $journal->reversalOf)); ?>"><?php echo e($journal->reversalOf->number); ?></a></dd>
                    <?php endif; ?>
                    <?php if($reversal !== null): ?>
                        <dt class="text-gray-500"><?php echo e(__('ui.accounting.reversed')); ?></dt>
                        <dd class="font-medium text-gray-800"><a class="text-lentera-800 underline-offset-2 hover:underline" href="<?php echo e(route('journals.show', $reversal)); ?>"><?php echo e($reversal->number); ?></a></dd>
                    <?php endif; ?>
                </dl>
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.accounting.account')); ?></th>
                            <th class="px-4 py-3 font-medium"><?php echo e(__('ui.stock.branch')); ?></th>
                            <th class="px-4 py-3 text-right font-medium"><?php echo e(__('ui.accounting.debit')); ?></th>
                            <th class="px-4 py-3 text-right font-medium"><?php echo e(__('ui.accounting.credit')); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__currentLoopData = $journal->lines; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $line): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 font-medium text-gray-800"><?php echo e($line->account->code); ?> — <?php echo e($line->account->name); ?></td>
                                <td class="px-4 py-3"><?php echo e($line->branch?->name ?? '—'); ?></td>
                                <td class="px-4 py-3 text-right tnum"><?php echo e(\App\Support\Money::fromDecimal((string) $line->debit)->format()); ?></td>
                                <td class="px-4 py-3 text-right tnum"><?php echo e(\App\Support\Money::fromDecimal((string) $line->credit)->format()); ?></td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        <tr class="bg-gray-50 font-medium">
                            <td class="px-4 py-3" colspan="2"><?php echo e(__('ui.accounting.total')); ?></td>
                            <td class="px-4 py-3 text-right tnum"><?php echo e($journal->totalDebit()->format()); ?></td>
                            <td class="px-4 py-3 text-right tnum"><?php echo e($journal->totalDebit()->format()); ?></td>
                        </tr>
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
<?php /**PATH /home/hatch/workspace/lentera/resources/views/accounting/journals/show.blade.php ENDPATH**/ ?>