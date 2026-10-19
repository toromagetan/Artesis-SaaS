
<?php
    $u = Auth::user();
    $can = fn (string $p) => $u->hasPermission($p);
    $item = 'relative flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-left text-sm font-medium text-lentera-100/80 transition duration-150 hover:bg-white/5 hover:text-white';
    $itemActive = 'bg-lentera-800 font-semibold text-white';
    $sub = 'flex items-center gap-2 rounded-lg py-1.5 pl-11 pr-3 text-[13px] text-lentera-100/60 transition duration-150 hover:bg-white/5 hover:text-white';
    $subActive = 'font-semibold text-white';
    $chev = 'ml-auto h-4 w-4 shrink-0 opacity-60 transition-transform duration-150';
    $ico = 'h-[18px] w-[18px] shrink-0 opacity-80';
    $goldBar = '<span class="absolute -left-3 bottom-1.5 top-1.5 w-[3px] rounded bg-gold"></span>';
    $dot = '<span class="h-[5px] w-[5px] shrink-0 rounded-full bg-lentera-100/30"></span>';
    $dotActive = '<span class="h-[5px] w-[5px] shrink-0 rounded-full bg-gold"></span>';
?>

<div x-show="sidebar" x-cloak @click="sidebar = false" class="fixed inset-0 z-30 bg-lentera-950/50 lg:hidden"></div>

<aside :class="sidebar ? 'translate-x-0' : '-translate-x-full'"
       class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col bg-lentera-950 shadow-soft transition-transform duration-200 lg:sticky lg:top-0 lg:h-screen lg:shrink-0 lg:translate-x-0">
    
    <div class="flex shrink-0 items-center gap-2.5 border-b border-white/10 px-5 pb-4 pt-5">
        <a href="<?php echo e(route('dashboard')); ?>" class="flex items-center gap-2.5">
            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-lentera-800 ring-1 ring-gold/50">
                <span class="h-3 w-3 rounded-full bg-gold"></span>
            </span>
            <span>
                <span class="block text-[17px] font-extrabold leading-tight tracking-tight text-white">Lentera</span>
                <span class="block text-[10.5px] leading-tight text-lentera-300/70"><?php echo e(__('ui.nav.tagline')); ?></span>
            </span>
        </a>
        <button type="button" @click="sidebar = false" class="ml-auto rounded-lg p-1.5 text-lentera-100/60 transition hover:bg-white/5 hover:text-white lg:hidden" aria-label="Tutup menu">
            <svg class="h-5 w-5" stroke="currentColor" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
        </button>
    </div>

    
    <nav class="flex-1 overflow-y-auto px-3 pb-4">
        
        <p class="px-3 pb-1.5 pt-4 text-[11px] font-bold uppercase tracking-[0.08em] text-lentera-300/70"><?php echo e(__('ui.nav.group_main')); ?></p>
        <a href="<?php echo e(route('dashboard')); ?>" class="<?php echo e($item); ?> <?php echo e(request()->routeIs('dashboard') ? $itemActive : ''); ?>">
            <?php if(request()->routeIs('dashboard')): ?> <?php echo $goldBar; ?> <?php endif; ?>
            <svg class="<?php echo e($ico); ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg>
            <?php echo e(__('ui.nav.dashboard')); ?>

        </a>
        <?php if($u->hasRole('Owner')): ?>
            <a href="<?php echo e(route('assistant.index')); ?>" class="<?php echo e($item); ?> <?php echo e(request()->routeIs('assistant.*') ? $itemActive : ''); ?>">
                <?php if(request()->routeIs('assistant.*')): ?> <?php echo $goldBar; ?> <?php endif; ?>
                <svg class="<?php echo e($ico); ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z"/></svg>
                <?php echo e(__('ui.nav.assistant')); ?>

            </a>
        <?php endif; ?>

        
        <?php if($can('sales.view') || $can('customers.view') || $can('lab.view') || $can('stock.view')): ?>
            <p class="px-3 pb-1.5 pt-5 text-[11px] font-bold uppercase tracking-[0.08em] text-lentera-300/70"><?php echo e(__('ui.nav.group_operations')); ?></p>
            <?php if($can('sales.view')): ?>
                <?php $act = request()->routeIs('sales.*') || request()->routeIs('receivables.*'); ?>
                <div x-data="{ open: <?php echo e($act ? 'true' : 'false'); ?> }">
                    <button type="button" @click="open = !open" class="<?php echo e($item); ?> <?php echo e($act ? $itemActive : ''); ?>">
                        <?php if($act): ?> <?php echo $goldBar; ?> <?php endif; ?>
                        <svg class="<?php echo e($ico); ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="20" r="1.6"/><circle cx="17" cy="20" r="1.6"/><path d="M2 3h3l2.6 12.5a1.5 1.5 0 0 0 1.5 1.2h7.9a1.5 1.5 0 0 0 1.5-1.2L20.5 7H6"/></svg>
                        <?php echo e(__('ui.nav.sales')); ?>

                        <svg :class="open ? 'rotate-180' : ''" class="<?php echo e($chev); ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                    </button>
                    <div x-show="open" <?php if(! $act): ?> style="display: none;" <?php endif; ?> class="mt-0.5 space-y-0.5">
                        <a href="<?php echo e(route('sales.index')); ?>" class="<?php echo e($sub); ?> <?php echo e(request()->routeIs('sales.index') ? $subActive : ''); ?>"><?php if(request()->routeIs('sales.index')): ?> <?php echo $dotActive; ?> <?php else: ?> <?php echo $dot; ?> <?php endif; ?><?php echo e(__('ui.nav.sales_list')); ?></a>
                        <a href="<?php echo e(route('sales.create')); ?>" class="<?php echo e($sub); ?> <?php echo e(request()->routeIs('sales.create') ? $subActive : ''); ?>"><?php if(request()->routeIs('sales.create')): ?> <?php echo $dotActive; ?> <?php else: ?> <?php echo $dot; ?> <?php endif; ?><?php echo e(__('ui.nav.sales_create')); ?></a>
                        <a href="<?php echo e(route('receivables.index')); ?>" class="<?php echo e($sub); ?> <?php echo e(request()->routeIs('receivables.*') ? $subActive : ''); ?>"><?php if(request()->routeIs('receivables.*')): ?> <?php echo $dotActive; ?> <?php else: ?> <?php echo $dot; ?> <?php endif; ?><?php echo e(__('ui.nav.receivables')); ?></a>
                        <a href="<?php echo e(route('offline-exceptions.index')); ?>" class="<?php echo e($sub); ?> <?php echo e(request()->routeIs('offline-exceptions.*') ? $subActive : ''); ?>"><?php if(request()->routeIs('offline-exceptions.*')): ?> <?php echo $dotActive; ?> <?php else: ?> <?php echo $dot; ?> <?php endif; ?><?php echo e(__('ui.nav.offline_exceptions')); ?></a>
                        <a href="<?php echo e(route('warranties.index')); ?>" class="<?php echo e($sub); ?> <?php echo e(request()->routeIs('warranties.*') ? $subActive : ''); ?>"><?php if(request()->routeIs('warranties.*')): ?> <?php echo $dotActive; ?> <?php else: ?> <?php echo $dot; ?> <?php endif; ?><?php echo e(__('ui.nav.warranties')); ?></a>
                    </div>
                </div>
            <?php endif; ?>
            <?php if(! $can('sales.view') && $can('stock.view')): ?>
                <a href="<?php echo e(route('warranties.index')); ?>" class="<?php echo e($item); ?> <?php echo e(request()->routeIs('warranties.*') ? $itemActive : ''); ?>">
                    <?php if(request()->routeIs('warranties.*')): ?> <?php echo $goldBar; ?> <?php endif; ?>
                    <svg class="<?php echo e($ico); ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15L15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>
                    <?php echo e(__('ui.nav.warranties')); ?>

                </a>
            <?php endif; ?>
            <?php if($can('customers.view')): ?>
                <?php $act = request()->routeIs('customers.*') || request()->routeIs('prescriptions.*'); ?>
                <div x-data="{ open: <?php echo e($act ? 'true' : 'false'); ?> }">
                    <button type="button" @click="open = !open" class="<?php echo e($item); ?> <?php echo e($act ? $itemActive : ''); ?>">
                        <?php if($act): ?> <?php echo $goldBar; ?> <?php endif; ?>
                        <svg class="<?php echo e($ico); ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.9"/><path d="M16 3.1a4 4 0 0 1 0 7.8"/></svg>
                        <?php echo e(__('ui.nav.customers')); ?>

                        <svg :class="open ? 'rotate-180' : ''" class="<?php echo e($chev); ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                    </button>
                    <div x-show="open" <?php if(! $act): ?> style="display: none;" <?php endif; ?> class="mt-0.5 space-y-0.5">
                        <a href="<?php echo e(route('customers.index')); ?>" class="<?php echo e($sub); ?> <?php echo e(request()->routeIs('customers.index') ? $subActive : ''); ?>"><?php if(request()->routeIs('customers.index')): ?> <?php echo $dotActive; ?> <?php else: ?> <?php echo $dot; ?> <?php endif; ?><?php echo e(__('ui.nav.customers_list')); ?></a>
                        <a href="<?php echo e(route('customers.create')); ?>" class="<?php echo e($sub); ?> <?php echo e(request()->routeIs('customers.create') ? $subActive : ''); ?>"><?php if(request()->routeIs('customers.create')): ?> <?php echo $dotActive; ?> <?php else: ?> <?php echo $dot; ?> <?php endif; ?><?php echo e(__('ui.nav.customers_create')); ?></a>
                    </div>
                </div>
            <?php endif; ?>
            <?php if($can('members.view')): ?>
                <?php $act = request()->routeIs('members.*'); ?>
                <div x-data="{ open: <?php echo e($act ? 'true' : 'false'); ?> }">
                    <button type="button" @click="open = !open" class="<?php echo e($item); ?> <?php echo e($act ? $itemActive : ''); ?>">
                        <?php if($act): ?> <?php echo $goldBar; ?> <?php endif; ?>
                        <svg class="<?php echo e($ico); ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v10M9.2 9.6c.5-.8 1.5-1.2 2.8-1.2 1.7 0 2.9.9 2.9 2.1 0 2.9-5.8 1.6-5.8 4.4 0 1.2 1.3 2.1 3 2.1 1.4 0 2.4-.5 3-1.3"/></svg>
                        <?php echo e(__('ui.nav.members')); ?>

                        <svg :class="open ? 'rotate-180' : ''" class="<?php echo e($chev); ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                    </button>
                    <div x-show="open" <?php if(! $act): ?> style="display: none;" <?php endif; ?> class="mt-0.5 space-y-0.5">
                        <a href="<?php echo e(route('members.index')); ?>" class="<?php echo e($sub); ?> <?php echo e(request()->routeIs('members.index') ? $subActive : ''); ?>"><?php if(request()->routeIs('members.index')): ?> <?php echo $dotActive; ?> <?php else: ?> <?php echo $dot; ?> <?php endif; ?><?php echo e(__('ui.nav.members_list')); ?></a>
                        <a href="<?php echo e(route('members.follow-up')); ?>" class="<?php echo e($sub); ?> <?php echo e(request()->routeIs('members.follow-up') ? $subActive : ''); ?>"><?php if(request()->routeIs('members.follow-up')): ?> <?php echo $dotActive; ?> <?php else: ?> <?php echo $dot; ?> <?php endif; ?><?php echo e(__('ui.nav.follow_up')); ?></a>
                        <?php if($can('members.manage')): ?>
                            <a href="<?php echo e(route('members.levels')); ?>" class="<?php echo e($sub); ?> <?php echo e(request()->routeIs('members.levels*') ? $subActive : ''); ?>"><?php if(request()->routeIs('members.levels*')): ?> <?php echo $dotActive; ?> <?php else: ?> <?php echo $dot; ?> <?php endif; ?><?php echo e(__('ui.nav.member_levels')); ?></a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            <?php if($can('lab.view')): ?>
                <?php $act = request()->routeIs('lab.*') || request()->routeIs('qc-templates.*'); $isManager = $u->hasRole('Owner') || $u->hasRole('Admin'); ?>
                <div x-data="{ open: <?php echo e($act ? 'true' : 'false'); ?> }">
                    <button type="button" @click="open = !open" class="<?php echo e($item); ?> <?php echo e($act ? $itemActive : ''); ?>">
                        <?php if($act): ?> <?php echo $goldBar; ?> <?php endif; ?>
                        <svg class="<?php echo e($ico); ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="6" cy="12" r="4"/><circle cx="18" cy="12" r="4"/><path d="M10 12h4"/></svg>
                        <?php echo e(__('ui.nav.lab')); ?>

                        <svg :class="open ? 'rotate-180' : ''" class="<?php echo e($chev); ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                    </button>
                    <div x-show="open" <?php if(! $act): ?> style="display: none;" <?php endif; ?> class="mt-0.5 space-y-0.5">
                        <a href="<?php echo e(route('lab.index')); ?>" class="<?php echo e($sub); ?> <?php echo e(request()->routeIs('lab.index') || request()->routeIs('lab.show') ? $subActive : ''); ?>"><?php if(request()->routeIs('lab.index') || request()->routeIs('lab.show')): ?> <?php echo $dotActive; ?> <?php else: ?> <?php echo $dot; ?> <?php endif; ?><?php echo e(__('ui.lab.title')); ?></a>
                        <?php if($isManager): ?>
                            <a href="<?php echo e(route('qc-templates.index')); ?>" class="<?php echo e($sub); ?> <?php echo e(request()->routeIs('qc-templates.*') ? $subActive : ''); ?>"><?php if(request()->routeIs('qc-templates.*')): ?> <?php echo $dotActive; ?> <?php else: ?> <?php echo $dot; ?> <?php endif; ?><?php echo e(__('ui.nav.qc_templates')); ?></a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        
        <?php if($can('pr.view') || $can('po.view') || $can('payments.collective') || $can('stock.view')): ?>
            <p class="px-3 pb-1.5 pt-5 text-[11px] font-bold uppercase tracking-[0.08em] text-lentera-300/70"><?php echo e(__('ui.nav.group_purchasing_inventory')); ?></p>
            <?php if($can('pr.view') || $can('po.view') || $can('payments.collective')): ?>
                <?php $act = request()->routeIs('pr.*') || request()->routeIs('po.*') || request()->routeIs('receipts.*') || request()->routeIs('payments.*'); ?>
                <div x-data="{ open: <?php echo e($act ? 'true' : 'false'); ?> }">
                    <button type="button" @click="open = !open" class="<?php echo e($item); ?> <?php echo e($act ? $itemActive : ''); ?>">
                        <?php if($act): ?> <?php echo $goldBar; ?> <?php endif; ?>
                        <svg class="<?php echo e($ico); ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 7h15l-1.7 8.5a1.5 1.5 0 0 1-1.5 1.2H8.6a1.5 1.5 0 0 1-1.5-1.2L5 3H2"/><circle cx="9" cy="21" r="1.4"/><circle cx="17" cy="21" r="1.4"/></svg>
                        <?php echo e(__('ui.nav.purchasing')); ?>

                        <svg :class="open ? 'rotate-180' : ''" class="<?php echo e($chev); ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                    </button>
                    <div x-show="open" <?php if(! $act): ?> style="display: none;" <?php endif; ?> class="mt-0.5 space-y-0.5">
                        <?php if($can('pr.view')): ?>
                            <a href="<?php echo e(route('pr.index')); ?>" class="<?php echo e($sub); ?> <?php echo e(request()->routeIs('pr.*') ? $subActive : ''); ?>"><?php if(request()->routeIs('pr.*')): ?> <?php echo $dotActive; ?> <?php else: ?> <?php echo $dot; ?> <?php endif; ?><?php echo e(__('ui.purchasing.pr_title')); ?></a>
                        <?php endif; ?>
                        <?php if($can('po.view')): ?>
                            <a href="<?php echo e(route('po.index')); ?>" class="<?php echo e($sub); ?> <?php echo e(request()->routeIs('po.*') || request()->routeIs('receipts.*') ? $subActive : ''); ?>"><?php if(request()->routeIs('po.*') || request()->routeIs('receipts.*')): ?> <?php echo $dotActive; ?> <?php else: ?> <?php echo $dot; ?> <?php endif; ?><?php echo e(__('ui.purchasing.po_title')); ?></a>
                        <?php endif; ?>
                        <?php if($can('payments.collective')): ?>
                            <a href="<?php echo e(route('payments.collective')); ?>" class="<?php echo e($sub); ?> <?php echo e(request()->routeIs('payments.*') ? $subActive : ''); ?>"><?php if(request()->routeIs('payments.*')): ?> <?php echo $dotActive; ?> <?php else: ?> <?php echo $dot; ?> <?php endif; ?><?php echo e(__('ui.purchasing.collective')); ?></a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            <?php if($can('stock.view')): ?>
                <?php $act = request()->routeIs('stock.*') || request()->routeIs('transfers.*'); ?>
                <div x-data="{ open: <?php echo e($act ? 'true' : 'false'); ?> }">
                    <button type="button" @click="open = !open" class="<?php echo e($item); ?> <?php echo e($act ? $itemActive : ''); ?>">
                        <?php if($act): ?> <?php echo $goldBar; ?> <?php endif; ?>
                        <svg class="<?php echo e($ico); ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 8 12 3 3 8v8l9 5 9-5V8Z"/><path d="m3 8 9 5 9-5"/><path d="M12 13v8"/></svg>
                        <?php echo e(__('ui.nav.stock_group')); ?>

                        <svg :class="open ? 'rotate-180' : ''" class="<?php echo e($chev); ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                    </button>
                    <div x-show="open" <?php if(! $act): ?> style="display: none;" <?php endif; ?> class="mt-0.5 space-y-0.5">
                        <a href="<?php echo e(route('stock.index')); ?>" class="<?php echo e($sub); ?> <?php echo e(request()->routeIs('stock.index') ? $subActive : ''); ?>"><?php if(request()->routeIs('stock.index')): ?> <?php echo $dotActive; ?> <?php else: ?> <?php echo $dot; ?> <?php endif; ?><?php echo e(__('ui.nav.stock')); ?></a>
                        <a href="<?php echo e(route('stock.movements')); ?>" class="<?php echo e($sub); ?> <?php echo e(request()->routeIs('stock.movements') ? $subActive : ''); ?>"><?php if(request()->routeIs('stock.movements')): ?> <?php echo $dotActive; ?> <?php else: ?> <?php echo $dot; ?> <?php endif; ?><?php echo e(__('ui.nav.stock_movements')); ?></a>
                        <a href="<?php echo e(route('transfers.index')); ?>" class="<?php echo e($sub); ?> <?php echo e(request()->routeIs('transfers.*') ? $subActive : ''); ?>"><?php if(request()->routeIs('transfers.*')): ?> <?php echo $dotActive; ?> <?php else: ?> <?php echo $dot; ?> <?php endif; ?><?php echo e(__('ui.nav.transfers')); ?></a>
                        <?php if($u->can('stock.opname')): ?>
                            <a href="<?php echo e(route('opnames.index')); ?>" class="<?php echo e($sub); ?> <?php echo e(request()->routeIs('opnames.*') ? $subActive : ''); ?>"><?php if(request()->routeIs('opnames.*')): ?> <?php echo $dotActive; ?> <?php else: ?> <?php echo $dot; ?> <?php endif; ?><?php echo e(__('ui.nav.opname')); ?></a>
                        <?php endif; ?>
                        <a href="<?php echo e(route('labels.index')); ?>" class="<?php echo e($sub); ?> <?php echo e(request()->routeIs('labels.*') ? $subActive : ''); ?>"><?php if(request()->routeIs('labels.*')): ?> <?php echo $dotActive; ?> <?php else: ?> <?php echo $dot; ?> <?php endif; ?><?php echo e(__('ui.nav.labels')); ?></a>
                        <?php if($u->can('stock.import-initial')): ?>
                            <a href="<?php echo e(route('stock.import.form')); ?>" class="<?php echo e($sub); ?> <?php echo e(request()->routeIs('stock.import.*') ? $subActive : ''); ?>"><?php if(request()->routeIs('stock.import.*')): ?> <?php echo $dotActive; ?> <?php else: ?> <?php echo $dot; ?> <?php endif; ?><?php echo e(__('ui.import.title')); ?></a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        
        <?php if($can('journals.view') || $can('accounts.view') || $can('assets.manage') || $can('periods.lock') || $can('payroll.manage')): ?>
            <p class="px-3 pb-1.5 pt-5 text-[11px] font-bold uppercase tracking-[0.08em] text-lentera-300/70"><?php echo e(__('ui.nav.accounting')); ?></p>
            <?php if($can('journals.view')): ?>
                <?php $act = request()->routeIs('journals.*') || request()->routeIs('ledger.*'); ?>
                <div x-data="{ open: <?php echo e($act ? 'true' : 'false'); ?> }">
                    <button type="button" @click="open = !open" class="<?php echo e($item); ?> <?php echo e($act ? $itemActive : ''); ?>">
                        <?php if($act): ?> <?php echo $goldBar; ?> <?php endif; ?>
                        <svg class="<?php echo e($ico); ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V3H6.5A2.5 2.5 0 0 0 4 5.5v14Z"/><path d="M4 19.5A2.5 2.5 0 0 0 6.5 22H20v-5"/></svg>
                        <?php echo e(__('ui.nav.journals')); ?>

                        <svg :class="open ? 'rotate-180' : ''" class="<?php echo e($chev); ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                    </button>
                    <div x-show="open" <?php if(! $act): ?> style="display: none;" <?php endif; ?> class="mt-0.5 space-y-0.5">
                        <a href="<?php echo e(route('journals.index')); ?>" class="<?php echo e($sub); ?> <?php echo e(request()->routeIs('journals.index') ? $subActive : ''); ?>"><?php if(request()->routeIs('journals.index')): ?> <?php echo $dotActive; ?> <?php else: ?> <?php echo $dot; ?> <?php endif; ?><?php echo e(__('ui.nav.journals_list')); ?></a>
                        <a href="<?php echo e(route('journals.create')); ?>" class="<?php echo e($sub); ?> <?php echo e(request()->routeIs('journals.create') ? $subActive : ''); ?>"><?php if(request()->routeIs('journals.create')): ?> <?php echo $dotActive; ?> <?php else: ?> <?php echo $dot; ?> <?php endif; ?><?php echo e(__('ui.nav.journal_manual')); ?></a>
                        <a href="<?php echo e(route('ledger.index')); ?>" class="<?php echo e($sub); ?> <?php echo e(request()->routeIs('ledger.*') ? $subActive : ''); ?>"><?php if(request()->routeIs('ledger.*')): ?> <?php echo $dotActive; ?> <?php else: ?> <?php echo $dot; ?> <?php endif; ?><?php echo e(__('ui.nav.ledger')); ?></a>
                    </div>
                </div>
            <?php endif; ?>
            <?php if($can('accounts.view')): ?>
                <a href="<?php echo e(route('accounts.index')); ?>" class="<?php echo e($item); ?> <?php echo e(request()->routeIs('accounts.*') ? $itemActive : ''); ?>">
                    <?php if(request()->routeIs('accounts.*')): ?> <?php echo $goldBar; ?> <?php endif; ?>
                    <svg class="<?php echo e($ico); ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M3 12h18"/><path d="M3 18h12"/></svg>
                    <?php echo e(__('ui.accounting.accounts')); ?>

                </a>
            <?php endif; ?>
            <?php if($can('assets.manage')): ?>
                <a href="<?php echo e(route('assets.index')); ?>" class="<?php echo e($item); ?> <?php echo e(request()->routeIs('assets.*') ? $itemActive : ''); ?>">
                    <?php if(request()->routeIs('assets.*')): ?> <?php echo $goldBar; ?> <?php endif; ?>
                    <svg class="<?php echo e($ico); ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    <?php echo e(__('ui.nav.assets')); ?>

                </a>
            <?php endif; ?>
            <?php if($can('periods.lock')): ?>
                <a href="<?php echo e(route('periods.index')); ?>" class="<?php echo e($item); ?> <?php echo e(request()->routeIs('periods.*') ? $itemActive : ''); ?>">
                    <?php if(request()->routeIs('periods.*')): ?> <?php echo $goldBar; ?> <?php endif; ?>
                    <svg class="<?php echo e($ico); ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                    <?php echo e(__('ui.nav.periods')); ?>

                </a>
            <?php endif; ?>
            <?php if($can('payroll.manage')): ?>
                <?php $act = request()->routeIs('payroll.*') || request()->routeIs('commissions.*'); ?>
                <div x-data="{ open: <?php echo e($act ? 'true' : 'false'); ?> }">
                    <button type="button" @click="open = !open" class="<?php echo e($item); ?> <?php echo e($act ? $itemActive : ''); ?>">
                        <?php if($act): ?> <?php echo $goldBar; ?> <?php endif; ?>
                        <svg class="<?php echo e($ico); ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>
                        <?php echo e(__('ui.nav.payroll')); ?>

                        <svg :class="open ? 'rotate-180' : ''" class="<?php echo e($chev); ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                    </button>
                    <div x-show="open" <?php if(! $act): ?> style="display: none;" <?php endif; ?> class="mt-0.5 space-y-0.5">
                        <a href="<?php echo e(route('payroll.index')); ?>" class="<?php echo e($sub); ?> <?php echo e(request()->routeIs('payroll.*') ? $subActive : ''); ?>"><?php if(request()->routeIs('payroll.*')): ?> <?php echo $dotActive; ?> <?php else: ?> <?php echo $dot; ?> <?php endif; ?><?php echo e(__('ui.nav.payroll_item')); ?></a>
                        <a href="<?php echo e(route('commissions.index')); ?>" class="<?php echo e($sub); ?> <?php echo e(request()->routeIs('commissions.*') ? $subActive : ''); ?>"><?php if(request()->routeIs('commissions.*')): ?> <?php echo $dotActive; ?> <?php else: ?> <?php echo $dot; ?> <?php endif; ?><?php echo e(__('ui.nav.commissions_item')); ?></a>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        
        <?php if($can('reports.sales') || $can('reports.purchases') || $can('reports.stock') || $can('reports.financial')): ?>
            <p class="px-3 pb-1.5 pt-5 text-[11px] font-bold uppercase tracking-[0.08em] text-lentera-300/70"><?php echo e(__('ui.nav.reports')); ?></p>
            <?php if($can('reports.sales') || $can('reports.purchases') || $can('reports.stock')): ?>
                <?php $act = request()->routeIs('reports.*'); ?>
                <div x-data="{ open: <?php echo e($act ? 'true' : 'false'); ?> }">
                    <button type="button" @click="open = !open" class="<?php echo e($item); ?> <?php echo e($act ? $itemActive : ''); ?>">
                        <?php if($act): ?> <?php echo $goldBar; ?> <?php endif; ?>
                        <svg class="<?php echo e($ico); ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="M7 15v3"/><path d="M12 10v8"/><path d="M17 6v12"/></svg>
                        <?php echo e(__('ui.nav.reports_operations')); ?>

                        <svg :class="open ? 'rotate-180' : ''" class="<?php echo e($chev); ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                    </button>
                    <div x-show="open" <?php if(! $act): ?> style="display: none;" <?php endif; ?> class="mt-0.5 space-y-0.5">
                        <?php if($can('reports.sales')): ?>
                            <a href="<?php echo e(route('reports.sales')); ?>" class="<?php echo e($sub); ?> <?php echo e(request()->routeIs('reports.sales') ? $subActive : ''); ?>"><?php if(request()->routeIs('reports.sales')): ?> <?php echo $dotActive; ?> <?php else: ?> <?php echo $dot; ?> <?php endif; ?><?php echo e(__('ui.nav.sales')); ?></a>
                        <?php endif; ?>
                        <?php if($can('reports.purchases')): ?>
                            <a href="<?php echo e(route('reports.purchases')); ?>" class="<?php echo e($sub); ?> <?php echo e(request()->routeIs('reports.purchases') ? $subActive : ''); ?>"><?php if(request()->routeIs('reports.purchases')): ?> <?php echo $dotActive; ?> <?php else: ?> <?php echo $dot; ?> <?php endif; ?><?php echo e(__('ui.nav.purchasing')); ?></a>
                            <a href="<?php echo e(route('reports.payables')); ?>" class="<?php echo e($sub); ?> <?php echo e(request()->routeIs('reports.payables') ? $subActive : ''); ?>"><?php if(request()->routeIs('reports.payables')): ?> <?php echo $dotActive; ?> <?php else: ?> <?php echo $dot; ?> <?php endif; ?><?php echo e(__('ui.nav.rep_payables')); ?></a>
                        <?php endif; ?>
                        <?php if($can('reports.stock')): ?>
                            <a href="<?php echo e(route('reports.stock')); ?>" class="<?php echo e($sub); ?> <?php echo e(request()->routeIs('reports.stock') ? $subActive : ''); ?>"><?php if(request()->routeIs('reports.stock')): ?> <?php echo $dotActive; ?> <?php else: ?> <?php echo $dot; ?> <?php endif; ?><?php echo e(__('ui.nav.stock')); ?></a>
                        <?php endif; ?>
                        <?php if($can('reports.sales')): ?>
                            <a href="<?php echo e(route('reports.analytics')); ?>" class="<?php echo e($sub); ?> <?php echo e(request()->routeIs('reports.analytics') ? $subActive : ''); ?>"><?php if(request()->routeIs('reports.analytics')): ?> <?php echo $dotActive; ?> <?php else: ?> <?php echo $dot; ?> <?php endif; ?><?php echo e(__('ui.nav.analytics')); ?></a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            <?php if($can('reports.financial')): ?>
                <?php $act = request()->routeIs('financial.*'); ?>
                <div x-data="{ open: <?php echo e($act ? 'true' : 'false'); ?> }">
                    <button type="button" @click="open = !open" class="<?php echo e($item); ?> <?php echo e($act ? $itemActive : ''); ?>">
                        <?php if($act): ?> <?php echo $goldBar; ?> <?php endif; ?>
                        <svg class="<?php echo e($ico); ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="m7 14 4-4 3 3 5-6"/></svg>
                        <?php echo e(__('ui.nav.reports_finance')); ?>

                        <svg :class="open ? 'rotate-180' : ''" class="<?php echo e($chev); ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                    </button>
                    <div x-show="open" <?php if(! $act): ?> style="display: none;" <?php endif; ?> class="mt-0.5 space-y-0.5">
                        <a href="<?php echo e(route('financial.balance-sheet')); ?>" class="<?php echo e($sub); ?> <?php echo e(request()->routeIs('financial.balance-sheet') ? $subActive : ''); ?>"><?php if(request()->routeIs('financial.balance-sheet')): ?> <?php echo $dotActive; ?> <?php else: ?> <?php echo $dot; ?> <?php endif; ?><?php echo e(__('ui.nav.fin_balance')); ?></a>
                        <a href="<?php echo e(route('financial.income')); ?>" class="<?php echo e($sub); ?> <?php echo e(request()->routeIs('financial.income') ? $subActive : ''); ?>"><?php if(request()->routeIs('financial.income')): ?> <?php echo $dotActive; ?> <?php else: ?> <?php echo $dot; ?> <?php endif; ?><?php echo e(__('ui.nav.fin_income')); ?></a>
                        <a href="<?php echo e(route('financial.cash-flow')); ?>" class="<?php echo e($sub); ?> <?php echo e(request()->routeIs('financial.cash-flow') ? $subActive : ''); ?>"><?php if(request()->routeIs('financial.cash-flow')): ?> <?php echo $dotActive; ?> <?php else: ?> <?php echo $dot; ?> <?php endif; ?><?php echo e(__('ui.nav.fin_cashflow')); ?></a>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        
        <?php if($can('wa.send') || $can('wa.templates') || $can('wa.inbox') || $can('settings.manage')): ?>
            <p class="px-3 pb-1.5 pt-5 text-[11px] font-bold uppercase tracking-[0.08em] text-lentera-300/70"><?php echo e(__('ui.nav.communication')); ?></p>
            <?php $act = request()->routeIs('wa-*'); ?>
            <div x-data="{ open: <?php echo e($act ? 'true' : 'false'); ?> }">
                <button type="button" @click="open = !open" class="<?php echo e($item); ?> <?php echo e($act ? $itemActive : ''); ?>">
                    <?php if($act): ?> <?php echo $goldBar; ?> <?php endif; ?>
                    <svg class="<?php echo e($ico); ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 11.5a8.5 8.5 0 0 1-12.4 7.6L3 21l1.9-5.6A8.5 8.5 0 1 1 21 11.5Z"/></svg>
                    <?php echo e(__('ui.nav.whatsapp')); ?>

                    <svg :class="open ? 'rotate-180' : ''" class="<?php echo e($chev); ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                </button>
                <div x-show="open" <?php if(! $act): ?> style="display: none;" <?php endif; ?> class="mt-0.5 space-y-0.5">
                    <?php if($can('wa.inbox')): ?>
                        <a href="<?php echo e(route('wa-inbox.index')); ?>" class="<?php echo e($sub); ?> <?php echo e(request()->routeIs('wa-inbox.*') ? $subActive : ''); ?>"><?php if(request()->routeIs('wa-inbox.*')): ?> <?php echo $dotActive; ?> <?php else: ?> <?php echo $dot; ?> <?php endif; ?><?php echo e(__('ui.nav.wa_inbox')); ?></a>
                    <?php endif; ?>
                    <?php if($can('wa.campaigns')): ?>
                        <a href="<?php echo e(route('wa-campaigns.index')); ?>" class="<?php echo e($sub); ?> <?php echo e(request()->routeIs('wa-campaigns.*') ? $subActive : ''); ?>"><?php if(request()->routeIs('wa-campaigns.*')): ?> <?php echo $dotActive; ?> <?php else: ?> <?php echo $dot; ?> <?php endif; ?><?php echo e(__('ui.nav.wa_campaigns')); ?></a>
                    <?php endif; ?>
                    <?php if($can('wa.send')): ?>
                        <a href="<?php echo e(route('wa-log.index')); ?>" class="<?php echo e($sub); ?> <?php echo e(request()->routeIs('wa-log.*') ? $subActive : ''); ?>"><?php if(request()->routeIs('wa-log.*')): ?> <?php echo $dotActive; ?> <?php else: ?> <?php echo $dot; ?> <?php endif; ?><?php echo e(__('ui.nav.wa_log')); ?></a>
                    <?php endif; ?>
                    <?php if($can('wa.templates')): ?>
                        <a href="<?php echo e(route('wa-templates.index')); ?>" class="<?php echo e($sub); ?> <?php echo e(request()->routeIs('wa-templates.*') ? $subActive : ''); ?>"><?php if(request()->routeIs('wa-templates.*')): ?> <?php echo $dotActive; ?> <?php else: ?> <?php echo $dot; ?> <?php endif; ?><?php echo e(__('ui.nav.wa_templates')); ?></a>
                    <?php endif; ?>
                    <?php if($can('settings.manage')): ?>
                        <a href="<?php echo e(route('wa-gateway.edit')); ?>" class="<?php echo e($sub); ?> <?php echo e(request()->routeIs('wa-gateway.*') ? $subActive : ''); ?>"><?php if(request()->routeIs('wa-gateway.*')): ?> <?php echo $dotActive; ?> <?php else: ?> <?php echo $dot; ?> <?php endif; ?><?php echo e(__('ui.nav.wa_gateway')); ?></a>
                        <a href="<?php echo e(route('ai-setting.edit')); ?>" class="<?php echo e($sub); ?> <?php echo e(request()->routeIs('ai-setting.*') ? $subActive : ''); ?>"><?php if(request()->routeIs('ai-setting.*')): ?> <?php echo $dotActive; ?> <?php else: ?> <?php echo $dot; ?> <?php endif; ?><?php echo e(__('ui.nav.ai_settings')); ?></a>
                        <a href="<?php echo e(route('audit-logs.index')); ?>" class="<?php echo e($sub); ?> <?php echo e(request()->routeIs('audit-logs.*') ? $subActive : ''); ?>"><?php if(request()->routeIs('audit-logs.*')): ?> <?php echo $dotActive; ?> <?php else: ?> <?php echo $dot; ?> <?php endif; ?><?php echo e(__('ui.nav.audit_logs')); ?></a>
                    <?php endif; ?>
                    <?php if($can('api_tokens.manage')): ?>
                        <a href="<?php echo e(route('api-tokens.index')); ?>" class="<?php echo e($sub); ?> <?php echo e(request()->routeIs('api-tokens.*') ? $subActive : ''); ?>"><?php if(request()->routeIs('api-tokens.*')): ?> <?php echo $dotActive; ?> <?php else: ?> <?php echo $dot; ?> <?php endif; ?><?php echo e(__('ui.nav.api_tokens')); ?></a>
                        <a href="<?php echo e(route('offline-devices.index')); ?>" class="<?php echo e($sub); ?> <?php echo e(request()->routeIs('offline-devices.*') ? $subActive : ''); ?>"><?php if(request()->routeIs('offline-devices.*')): ?> <?php echo $dotActive; ?> <?php else: ?> <?php echo $dot; ?> <?php endif; ?><?php echo e(__('ui.nav.offline_devices')); ?></a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        
        <?php if($can('products.view') || $can('users.manage') || $can('roles.manage') || $can('branches.manage')): ?>
            <p class="px-3 pb-1.5 pt-5 text-[11px] font-bold uppercase tracking-[0.08em] text-lentera-300/70"><?php echo e(__('ui.nav.group_master_settings')); ?></p>
            <?php if($can('products.view')): ?>
                <?php $act = request()->routeIs('products.*'); ?>
                <div x-data="{ open: <?php echo e($act ? 'true' : 'false'); ?> }">
                    <button type="button" @click="open = !open" class="<?php echo e($item); ?> <?php echo e($act ? $itemActive : ''); ?>">
                        <?php if($act): ?> <?php echo $goldBar; ?> <?php endif; ?>
                        <svg class="<?php echo e($ico); ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m12 2 9 5-9 5-9-5 9-5Z"/><path d="m3 12 9 5 9-5"/><path d="m3 17 9 5 9-5"/></svg>
                        <?php echo e(__('ui.nav.catalog')); ?>

                        <svg :class="open ? 'rotate-180' : ''" class="<?php echo e($chev); ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                    </button>
                    <div x-show="open" <?php if(! $act): ?> style="display: none;" <?php endif; ?> class="mt-0.5 space-y-0.5">
                        <?php $__currentLoopData = \App\Modules\Catalog\Models\Product::TYPES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $t): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <a href="<?php echo e(route('products.index', $t)); ?>" class="<?php echo e($sub); ?> <?php echo e(request()->routeIs('products.index') && request()->route('type') === $t ? $subActive : ''); ?>"><?php if(request()->routeIs('products.index') && request()->route('type') === $t): ?> <?php echo $dotActive; ?> <?php else: ?> <?php echo $dot; ?> <?php endif; ?><?php echo e(__('ui.products.types.'.$t)); ?></a>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                </div>
            <?php endif; ?>
            <?php if($can('users.manage')): ?>
                <a href="<?php echo e(route('users.index')); ?>" class="<?php echo e($item); ?> <?php echo e(request()->routeIs('users.*') ? $itemActive : ''); ?>">
                    <?php if(request()->routeIs('users.*')): ?> <?php echo $goldBar; ?> <?php endif; ?>
                    <svg class="<?php echo e($ico); ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 4.6a3.5 3.5 0 0 1 0 6.8"/><path d="M17.5 14.4a6.5 6.5 0 0 1 4 5.6"/></svg>
                    <?php echo e(__('ui.nav.users')); ?>

                </a>
            <?php endif; ?>
            <?php if($can('roles.manage')): ?>
                <a href="<?php echo e(route('roles.index')); ?>" class="<?php echo e($item); ?> <?php echo e(request()->routeIs('roles.*') ? $itemActive : ''); ?>">
                    <?php if(request()->routeIs('roles.*')): ?> <?php echo $goldBar; ?> <?php endif; ?>
                    <svg class="<?php echo e($ico); ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3 4 6v6c0 4.5 3.4 7.7 8 9 4.6-1.3 8-4.5 8-9V6l-8-3Z"/><path d="m9 12 2 2 4-4"/></svg>
                    <?php echo e(__('ui.nav.roles')); ?>

                </a>
            <?php endif; ?>
            <?php if($can('branches.manage')): ?>
                <a href="<?php echo e(route('branches.index')); ?>" class="<?php echo e($item); ?> <?php echo e(request()->routeIs('branches.*') ? $itemActive : ''); ?>">
                    <?php if(request()->routeIs('branches.*')): ?> <?php echo $goldBar; ?> <?php endif; ?>
                    <svg class="<?php echo e($ico); ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18"/><path d="M5 21V8l7-5 7 5v13"/><path d="M10 21v-4h4v4"/></svg>
                    <?php echo e(__('ui.nav.branches')); ?>

                </a>
            <?php endif; ?>
        <?php endif; ?>
    </nav>

    
    <div class="flex shrink-0 items-center gap-2.5 border-t border-white/10 px-5 py-3.5">
        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-lentera-800 text-xs font-bold text-white ring-1 ring-gold/50"><?php echo e(\Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($u->name, 0, 1))); ?></span>
        <span class="min-w-0">
            <span class="block truncate text-[13px] font-semibold leading-tight text-white"><?php echo e($u->name); ?></span>
            <span class="block text-[11px] leading-tight text-lentera-300/70"><?php echo e($u->roles->first()?->label ?? ''); ?></span>
        </span>
    </div>
</aside>
<?php /**PATH /home/hatch/workspace/lentera/resources/views/layouts/sidebar.blade.php ENDPATH**/ ?>