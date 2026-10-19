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
        <h1 class="text-xl font-semibold leading-tight text-lentera-950"><?php echo e(__('ui.dashboard.title')); ?></h1>
     <?php $__env->endSlot(); ?>

    <div class="py-8" x-data="dashboardDinamis('<?php echo e($period); ?>')" x-init="mulai()">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div class="inline-flex rounded-lg bg-white p-1 shadow-soft">
                    <?php $__currentLoopData = ['today' => 'period_today', '7d' => 'period_7d', '30d' => 'period_30d', 'month' => 'period_month']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $labelKey): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <button type="button" @click="aturPeriode('<?php echo e($key); ?>')"
                            :class="periode === '<?php echo e($key); ?>' ? 'bg-lentera-700 text-white' : 'text-gray-500 hover:text-lentera-800'"
                            class="rounded-md px-3 py-1.5 text-sm font-medium transition duration-150">
                            <?php echo e(__('ui.dashboard.'.$labelKey)); ?>

                        </button>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
                <p class="text-xs text-gray-400"><?php echo e(__('ui.dashboard.updated_at')); ?> <span class="tnum" x-text="diperbarui"><?php echo e(now()->format('H:i:s')); ?></span></p>
            </div>

            <div id="dashboard-cards">
                <?php echo $__env->make('dashboard.partials.cards', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            </div>

            <div id="dashboard-charts" class="mt-4">
                <?php echo $__env->make('dashboard.partials.charts', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('dashboardDinamis', (awal) => ({
                periode: awal,
                diperbarui: '<?php echo e(now()->format('H:i:s')); ?>',
                mulai() {
                    setInterval(() => this.muat(), 60000);
                },
                aturPeriode(p) {
                    this.periode = p;
                    this.muat();
                },
                async muat() {
                    try {
                        const r = await fetch('/dashboard/data?periode=' + this.periode, { headers: { 'Accept': 'application/json' } });
                        if (!r.ok) return;
                        const d = await r.json();
                        document.getElementById('dashboard-cards').innerHTML = d.cards_html;
                        document.getElementById('dashboard-charts').innerHTML = d.charts_html;
                        this.diperbarui = d.updated_at;
                    } catch (e) { /* abaikan kegagalan sesaat; interval berikutnya mencoba lagi */ }
                },
            }));
        });
    </script>
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
<?php /**PATH /home/hatch/workspace/lentera/resources/views/dashboard.blade.php ENDPATH**/ ?>