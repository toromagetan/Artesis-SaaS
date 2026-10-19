{{-- Sidebar Lentera (keputusan owner 2026-10-18, ADR-013 — menggantikan
     Varian A header): navigasi diklasifikasikan 6 kelompok dengan submenu
     buka-tutup; setiap item tampil hanya bila izinnya dipegang pengguna.
     Pratinjau yang disetujui: docs/preview/pratinjau-sidebar.html --}}
@php
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
@endphp

<div x-show="sidebar" x-cloak @click="sidebar = false" class="fixed inset-0 z-30 bg-lentera-950/50 lg:hidden"></div>

<aside :class="sidebar ? 'translate-x-0' : '-translate-x-full'"
       class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col bg-lentera-950 shadow-soft transition-transform duration-200 lg:sticky lg:top-0 lg:h-screen lg:shrink-0 lg:translate-x-0">
    {{-- Merek --}}
    <div class="flex shrink-0 items-center gap-2.5 border-b border-white/10 px-5 pb-4 pt-5">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5">
            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-lentera-800 ring-1 ring-gold/50">
                <span class="h-3 w-3 rounded-full bg-gold"></span>
            </span>
            <span>
                <span class="block text-[17px] font-extrabold leading-tight tracking-tight text-white">Lentera</span>
                <span class="block text-[10.5px] leading-tight text-lentera-300/70">{{ __('ui.nav.tagline') }}</span>
            </span>
        </a>
        <button type="button" @click="sidebar = false" class="ml-auto rounded-lg p-1.5 text-lentera-100/60 transition hover:bg-white/5 hover:text-white lg:hidden" aria-label="Tutup menu">
            <svg class="h-5 w-5" stroke="currentColor" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
        </button>
    </div>

    {{-- Navigasi terklasifikasi --}}
    <nav class="flex-1 overflow-y-auto px-3 pb-4">
        {{-- UTAMA --}}
        <p class="px-3 pb-1.5 pt-4 text-[11px] font-bold uppercase tracking-[0.08em] text-lentera-300/70">{{ __('ui.nav.group_main') }}</p>
        <a href="{{ route('dashboard') }}" class="{{ $item }} {{ request()->routeIs('dashboard') ? $itemActive : '' }}">
            @if (request()->routeIs('dashboard')) {!! $goldBar !!} @endif
            <svg class="{{ $ico }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg>
            {{ __('ui.nav.dashboard') }}
        </a>
        @if ($u->hasRole('Owner'))
            <a href="{{ route('assistant.index') }}" class="{{ $item }} {{ request()->routeIs('assistant.*') ? $itemActive : '' }}">
                @if (request()->routeIs('assistant.*')) {!! $goldBar !!} @endif
                <svg class="{{ $ico }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z"/></svg>
                {{ __('ui.nav.assistant') }}
            </a>
        @endif

        {{-- OPERASIONAL --}}
        @if ($can('sales.view') || $can('customers.view') || $can('lab.view') || $can('stock.view'))
            <p class="px-3 pb-1.5 pt-5 text-[11px] font-bold uppercase tracking-[0.08em] text-lentera-300/70">{{ __('ui.nav.group_operations') }}</p>
            @if ($can('sales.view'))
                @php $act = request()->routeIs('sales.*') || request()->routeIs('receivables.*'); @endphp
                <div x-data="{ open: {{ $act ? 'true' : 'false' }} }">
                    <button type="button" @click="open = !open" class="{{ $item }} {{ $act ? $itemActive : '' }}">
                        @if ($act) {!! $goldBar !!} @endif
                        <svg class="{{ $ico }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="20" r="1.6"/><circle cx="17" cy="20" r="1.6"/><path d="M2 3h3l2.6 12.5a1.5 1.5 0 0 0 1.5 1.2h7.9a1.5 1.5 0 0 0 1.5-1.2L20.5 7H6"/></svg>
                        {{ __('ui.nav.sales') }}
                        <svg :class="open ? 'rotate-180' : ''" class="{{ $chev }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                    </button>
                    <div x-show="open" @if (! $act) style="display: none;" @endif class="mt-0.5 space-y-0.5">
                        <a href="{{ route('sales.index') }}" class="{{ $sub }} {{ request()->routeIs('sales.index') ? $subActive : '' }}">@if (request()->routeIs('sales.index')) {!! $dotActive !!} @else {!! $dot !!} @endif{{ __('ui.nav.sales_list') }}</a>
                        <a href="{{ route('sales.create') }}" class="{{ $sub }} {{ request()->routeIs('sales.create') ? $subActive : '' }}">@if (request()->routeIs('sales.create')) {!! $dotActive !!} @else {!! $dot !!} @endif{{ __('ui.nav.sales_create') }}</a>
                        <a href="{{ route('receivables.index') }}" class="{{ $sub }} {{ request()->routeIs('receivables.*') ? $subActive : '' }}">@if (request()->routeIs('receivables.*')) {!! $dotActive !!} @else {!! $dot !!} @endif{{ __('ui.nav.receivables') }}</a>
                        <a href="{{ route('offline-exceptions.index') }}" class="{{ $sub }} {{ request()->routeIs('offline-exceptions.*') ? $subActive : '' }}">@if (request()->routeIs('offline-exceptions.*')) {!! $dotActive !!} @else {!! $dot !!} @endif{{ __('ui.nav.offline_exceptions') }}</a>
                        <a href="{{ route('warranties.index') }}" class="{{ $sub }} {{ request()->routeIs('warranties.*') ? $subActive : '' }}">@if (request()->routeIs('warranties.*')) {!! $dotActive !!} @else {!! $dot !!} @endif{{ __('ui.nav.warranties') }}</a>
                    </div>
                </div>
            @endif
            @if (! $can('sales.view') && $can('stock.view'))
                <a href="{{ route('warranties.index') }}" class="{{ $item }} {{ request()->routeIs('warranties.*') ? $itemActive : '' }}">
                    @if (request()->routeIs('warranties.*')) {!! $goldBar !!} @endif
                    <svg class="{{ $ico }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15L15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>
                    {{ __('ui.nav.warranties') }}
                </a>
            @endif
            @if ($can('customers.view'))
                @php $act = request()->routeIs('customers.*') || request()->routeIs('prescriptions.*'); @endphp
                <div x-data="{ open: {{ $act ? 'true' : 'false' }} }">
                    <button type="button" @click="open = !open" class="{{ $item }} {{ $act ? $itemActive : '' }}">
                        @if ($act) {!! $goldBar !!} @endif
                        <svg class="{{ $ico }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.9"/><path d="M16 3.1a4 4 0 0 1 0 7.8"/></svg>
                        {{ __('ui.nav.customers') }}
                        <svg :class="open ? 'rotate-180' : ''" class="{{ $chev }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                    </button>
                    <div x-show="open" @if (! $act) style="display: none;" @endif class="mt-0.5 space-y-0.5">
                        <a href="{{ route('customers.index') }}" class="{{ $sub }} {{ request()->routeIs('customers.index') ? $subActive : '' }}">@if (request()->routeIs('customers.index')) {!! $dotActive !!} @else {!! $dot !!} @endif{{ __('ui.nav.customers_list') }}</a>
                        <a href="{{ route('customers.create') }}" class="{{ $sub }} {{ request()->routeIs('customers.create') ? $subActive : '' }}">@if (request()->routeIs('customers.create')) {!! $dotActive !!} @else {!! $dot !!} @endif{{ __('ui.nav.customers_create') }}</a>
                    </div>
                </div>
            @endif
            @if ($can('members.view'))
                @php $act = request()->routeIs('members.*'); @endphp
                <div x-data="{ open: {{ $act ? 'true' : 'false' }} }">
                    <button type="button" @click="open = !open" class="{{ $item }} {{ $act ? $itemActive : '' }}">
                        @if ($act) {!! $goldBar !!} @endif
                        <svg class="{{ $ico }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v10M9.2 9.6c.5-.8 1.5-1.2 2.8-1.2 1.7 0 2.9.9 2.9 2.1 0 2.9-5.8 1.6-5.8 4.4 0 1.2 1.3 2.1 3 2.1 1.4 0 2.4-.5 3-1.3"/></svg>
                        {{ __('ui.nav.members') }}
                        <svg :class="open ? 'rotate-180' : ''" class="{{ $chev }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                    </button>
                    <div x-show="open" @if (! $act) style="display: none;" @endif class="mt-0.5 space-y-0.5">
                        <a href="{{ route('members.index') }}" class="{{ $sub }} {{ request()->routeIs('members.index') ? $subActive : '' }}">@if (request()->routeIs('members.index')) {!! $dotActive !!} @else {!! $dot !!} @endif{{ __('ui.nav.members_list') }}</a>
                        <a href="{{ route('members.follow-up') }}" class="{{ $sub }} {{ request()->routeIs('members.follow-up') ? $subActive : '' }}">@if (request()->routeIs('members.follow-up')) {!! $dotActive !!} @else {!! $dot !!} @endif{{ __('ui.nav.follow_up') }}</a>
                        @if ($can('members.manage'))
                            <a href="{{ route('members.levels') }}" class="{{ $sub }} {{ request()->routeIs('members.levels*') ? $subActive : '' }}">@if (request()->routeIs('members.levels*')) {!! $dotActive !!} @else {!! $dot !!} @endif{{ __('ui.nav.member_levels') }}</a>
                        @endif
                    </div>
                </div>
            @endif
            @if ($can('lab.view'))
                @php $act = request()->routeIs('lab.*') || request()->routeIs('qc-templates.*'); $isManager = $u->hasRole('Owner') || $u->hasRole('Admin'); @endphp
                <div x-data="{ open: {{ $act ? 'true' : 'false' }} }">
                    <button type="button" @click="open = !open" class="{{ $item }} {{ $act ? $itemActive : '' }}">
                        @if ($act) {!! $goldBar !!} @endif
                        <svg class="{{ $ico }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="6" cy="12" r="4"/><circle cx="18" cy="12" r="4"/><path d="M10 12h4"/></svg>
                        {{ __('ui.nav.lab') }}
                        <svg :class="open ? 'rotate-180' : ''" class="{{ $chev }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                    </button>
                    <div x-show="open" @if (! $act) style="display: none;" @endif class="mt-0.5 space-y-0.5">
                        <a href="{{ route('lab.index') }}" class="{{ $sub }} {{ request()->routeIs('lab.index') || request()->routeIs('lab.show') ? $subActive : '' }}">@if (request()->routeIs('lab.index') || request()->routeIs('lab.show')) {!! $dotActive !!} @else {!! $dot !!} @endif{{ __('ui.lab.title') }}</a>
                        @if ($isManager)
                            <a href="{{ route('qc-templates.index') }}" class="{{ $sub }} {{ request()->routeIs('qc-templates.*') ? $subActive : '' }}">@if (request()->routeIs('qc-templates.*')) {!! $dotActive !!} @else {!! $dot !!} @endif{{ __('ui.nav.qc_templates') }}</a>
                        @endif
                    </div>
                </div>
            @endif
        @endif

        {{-- PEMBELIAN & PERSEDIAAN --}}
        @if ($can('pr.view') || $can('po.view') || $can('payments.collective') || $can('stock.view'))
            <p class="px-3 pb-1.5 pt-5 text-[11px] font-bold uppercase tracking-[0.08em] text-lentera-300/70">{{ __('ui.nav.group_purchasing_inventory') }}</p>
            @if ($can('pr.view') || $can('po.view') || $can('payments.collective'))
                @php $act = request()->routeIs('pr.*') || request()->routeIs('po.*') || request()->routeIs('receipts.*') || request()->routeIs('payments.*'); @endphp
                <div x-data="{ open: {{ $act ? 'true' : 'false' }} }">
                    <button type="button" @click="open = !open" class="{{ $item }} {{ $act ? $itemActive : '' }}">
                        @if ($act) {!! $goldBar !!} @endif
                        <svg class="{{ $ico }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 7h15l-1.7 8.5a1.5 1.5 0 0 1-1.5 1.2H8.6a1.5 1.5 0 0 1-1.5-1.2L5 3H2"/><circle cx="9" cy="21" r="1.4"/><circle cx="17" cy="21" r="1.4"/></svg>
                        {{ __('ui.nav.purchasing') }}
                        <svg :class="open ? 'rotate-180' : ''" class="{{ $chev }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                    </button>
                    <div x-show="open" @if (! $act) style="display: none;" @endif class="mt-0.5 space-y-0.5">
                        @if ($can('pr.view'))
                            <a href="{{ route('pr.index') }}" class="{{ $sub }} {{ request()->routeIs('pr.*') ? $subActive : '' }}">@if (request()->routeIs('pr.*')) {!! $dotActive !!} @else {!! $dot !!} @endif{{ __('ui.purchasing.pr_title') }}</a>
                        @endif
                        @if ($can('po.view'))
                            <a href="{{ route('po.index') }}" class="{{ $sub }} {{ request()->routeIs('po.*') || request()->routeIs('receipts.*') ? $subActive : '' }}">@if (request()->routeIs('po.*') || request()->routeIs('receipts.*')) {!! $dotActive !!} @else {!! $dot !!} @endif{{ __('ui.purchasing.po_title') }}</a>
                        @endif
                        @if ($can('payments.collective'))
                            <a href="{{ route('payments.collective') }}" class="{{ $sub }} {{ request()->routeIs('payments.*') ? $subActive : '' }}">@if (request()->routeIs('payments.*')) {!! $dotActive !!} @else {!! $dot !!} @endif{{ __('ui.purchasing.collective') }}</a>
                        @endif
                    </div>
                </div>
            @endif
            @if ($can('stock.view'))
                @php $act = request()->routeIs('stock.*') || request()->routeIs('transfers.*'); @endphp
                <div x-data="{ open: {{ $act ? 'true' : 'false' }} }">
                    <button type="button" @click="open = !open" class="{{ $item }} {{ $act ? $itemActive : '' }}">
                        @if ($act) {!! $goldBar !!} @endif
                        <svg class="{{ $ico }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 8 12 3 3 8v8l9 5 9-5V8Z"/><path d="m3 8 9 5 9-5"/><path d="M12 13v8"/></svg>
                        {{ __('ui.nav.stock_group') }}
                        <svg :class="open ? 'rotate-180' : ''" class="{{ $chev }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                    </button>
                    <div x-show="open" @if (! $act) style="display: none;" @endif class="mt-0.5 space-y-0.5">
                        <a href="{{ route('stock.index') }}" class="{{ $sub }} {{ request()->routeIs('stock.index') ? $subActive : '' }}">@if (request()->routeIs('stock.index')) {!! $dotActive !!} @else {!! $dot !!} @endif{{ __('ui.nav.stock') }}</a>
                        <a href="{{ route('stock.movements') }}" class="{{ $sub }} {{ request()->routeIs('stock.movements') ? $subActive : '' }}">@if (request()->routeIs('stock.movements')) {!! $dotActive !!} @else {!! $dot !!} @endif{{ __('ui.nav.stock_movements') }}</a>
                        <a href="{{ route('transfers.index') }}" class="{{ $sub }} {{ request()->routeIs('transfers.*') ? $subActive : '' }}">@if (request()->routeIs('transfers.*')) {!! $dotActive !!} @else {!! $dot !!} @endif{{ __('ui.nav.transfers') }}</a>
                        @if ($u->can('stock.opname'))
                            <a href="{{ route('opnames.index') }}" class="{{ $sub }} {{ request()->routeIs('opnames.*') ? $subActive : '' }}">@if (request()->routeIs('opnames.*')) {!! $dotActive !!} @else {!! $dot !!} @endif{{ __('ui.nav.opname') }}</a>
                        @endif
                        <a href="{{ route('labels.index') }}" class="{{ $sub }} {{ request()->routeIs('labels.*') ? $subActive : '' }}">@if (request()->routeIs('labels.*')) {!! $dotActive !!} @else {!! $dot !!} @endif{{ __('ui.nav.labels') }}</a>
                        @if ($u->can('stock.import-initial'))
                            <a href="{{ route('stock.import.form') }}" class="{{ $sub }} {{ request()->routeIs('stock.import.*') ? $subActive : '' }}">@if (request()->routeIs('stock.import.*')) {!! $dotActive !!} @else {!! $dot !!} @endif{{ __('ui.import.title') }}</a>
                        @endif
                    </div>
                </div>
            @endif
        @endif

        {{-- AKUNTANSI --}}
        @if ($can('journals.view') || $can('accounts.view') || $can('assets.manage') || $can('periods.lock') || $can('payroll.manage'))
            <p class="px-3 pb-1.5 pt-5 text-[11px] font-bold uppercase tracking-[0.08em] text-lentera-300/70">{{ __('ui.nav.accounting') }}</p>
            @if ($can('journals.view'))
                @php $act = request()->routeIs('journals.*') || request()->routeIs('ledger.*'); @endphp
                <div x-data="{ open: {{ $act ? 'true' : 'false' }} }">
                    <button type="button" @click="open = !open" class="{{ $item }} {{ $act ? $itemActive : '' }}">
                        @if ($act) {!! $goldBar !!} @endif
                        <svg class="{{ $ico }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V3H6.5A2.5 2.5 0 0 0 4 5.5v14Z"/><path d="M4 19.5A2.5 2.5 0 0 0 6.5 22H20v-5"/></svg>
                        {{ __('ui.nav.journals') }}
                        <svg :class="open ? 'rotate-180' : ''" class="{{ $chev }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                    </button>
                    <div x-show="open" @if (! $act) style="display: none;" @endif class="mt-0.5 space-y-0.5">
                        <a href="{{ route('journals.index') }}" class="{{ $sub }} {{ request()->routeIs('journals.index') ? $subActive : '' }}">@if (request()->routeIs('journals.index')) {!! $dotActive !!} @else {!! $dot !!} @endif{{ __('ui.nav.journals_list') }}</a>
                        <a href="{{ route('journals.create') }}" class="{{ $sub }} {{ request()->routeIs('journals.create') ? $subActive : '' }}">@if (request()->routeIs('journals.create')) {!! $dotActive !!} @else {!! $dot !!} @endif{{ __('ui.nav.journal_manual') }}</a>
                        <a href="{{ route('ledger.index') }}" class="{{ $sub }} {{ request()->routeIs('ledger.*') ? $subActive : '' }}">@if (request()->routeIs('ledger.*')) {!! $dotActive !!} @else {!! $dot !!} @endif{{ __('ui.nav.ledger') }}</a>
                    </div>
                </div>
            @endif
            @if ($can('accounts.view'))
                <a href="{{ route('accounts.index') }}" class="{{ $item }} {{ request()->routeIs('accounts.*') ? $itemActive : '' }}">
                    @if (request()->routeIs('accounts.*')) {!! $goldBar !!} @endif
                    <svg class="{{ $ico }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M3 12h18"/><path d="M3 18h12"/></svg>
                    {{ __('ui.accounting.accounts') }}
                </a>
            @endif
            @if ($can('assets.manage'))
                <a href="{{ route('assets.index') }}" class="{{ $item }} {{ request()->routeIs('assets.*') ? $itemActive : '' }}">
                    @if (request()->routeIs('assets.*')) {!! $goldBar !!} @endif
                    <svg class="{{ $ico }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    {{ __('ui.nav.assets') }}
                </a>
            @endif
            @if ($can('periods.lock'))
                <a href="{{ route('periods.index') }}" class="{{ $item }} {{ request()->routeIs('periods.*') ? $itemActive : '' }}">
                    @if (request()->routeIs('periods.*')) {!! $goldBar !!} @endif
                    <svg class="{{ $ico }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                    {{ __('ui.nav.periods') }}
                </a>
            @endif
            @if ($can('payroll.manage'))
                @php $act = request()->routeIs('payroll.*') || request()->routeIs('commissions.*') || request()->routeIs('incentives.*'); @endphp
                <div x-data="{ open: {{ $act ? 'true' : 'false' }} }">
                    <button type="button" @click="open = !open" class="{{ $item }} {{ $act ? $itemActive : '' }}">
                        @if ($act) {!! $goldBar !!} @endif
                        <svg class="{{ $ico }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>
                        {{ __('ui.nav.payroll') }}
                        <svg :class="open ? 'rotate-180' : ''" class="{{ $chev }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                    </button>
                    <div x-show="open" @if (! $act) style="display: none;" @endif class="mt-0.5 space-y-0.5">
                        <a href="{{ route('payroll.index') }}" class="{{ $sub }} {{ request()->routeIs('payroll.*') ? $subActive : '' }}">@if (request()->routeIs('payroll.*')) {!! $dotActive !!} @else {!! $dot !!} @endif{{ __('ui.nav.payroll_item') }}</a>
                        <a href="{{ route('commissions.index') }}" class="{{ $sub }} {{ request()->routeIs('commissions.*') ? $subActive : '' }}">@if (request()->routeIs('commissions.*')) {!! $dotActive !!} @else {!! $dot !!} @endif{{ __('ui.nav.commissions_item') }}</a>
                        <a href="{{ route('incentives.index') }}" class="{{ $sub }} {{ request()->routeIs('incentives.*') ? $subActive : '' }}">@if (request()->routeIs('incentives.*')) {!! $dotActive !!} @else {!! $dot !!} @endif{{ __('ui.nav.incentives_item') }}</a>
                    </div>
                </div>
            @endif
        @endif

        {{-- LAPORAN --}}
        @if ($can('reports.sales') || $can('reports.purchases') || $can('reports.stock') || $can('reports.financial'))
            <p class="px-3 pb-1.5 pt-5 text-[11px] font-bold uppercase tracking-[0.08em] text-lentera-300/70">{{ __('ui.nav.reports') }}</p>
            @if ($can('reports.sales') || $can('reports.purchases') || $can('reports.stock'))
                @php $act = request()->routeIs('reports.*'); @endphp
                <div x-data="{ open: {{ $act ? 'true' : 'false' }} }">
                    <button type="button" @click="open = !open" class="{{ $item }} {{ $act ? $itemActive : '' }}">
                        @if ($act) {!! $goldBar !!} @endif
                        <svg class="{{ $ico }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="M7 15v3"/><path d="M12 10v8"/><path d="M17 6v12"/></svg>
                        {{ __('ui.nav.reports_operations') }}
                        <svg :class="open ? 'rotate-180' : ''" class="{{ $chev }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                    </button>
                    <div x-show="open" @if (! $act) style="display: none;" @endif class="mt-0.5 space-y-0.5">
                        @if ($can('reports.sales'))
                            <a href="{{ route('reports.sales') }}" class="{{ $sub }} {{ request()->routeIs('reports.sales') ? $subActive : '' }}">@if (request()->routeIs('reports.sales')) {!! $dotActive !!} @else {!! $dot !!} @endif{{ __('ui.nav.sales') }}</a>
                        @endif
                        @if ($can('reports.purchases'))
                            <a href="{{ route('reports.purchases') }}" class="{{ $sub }} {{ request()->routeIs('reports.purchases') ? $subActive : '' }}">@if (request()->routeIs('reports.purchases')) {!! $dotActive !!} @else {!! $dot !!} @endif{{ __('ui.nav.purchasing') }}</a>
                            <a href="{{ route('reports.payables') }}" class="{{ $sub }} {{ request()->routeIs('reports.payables') ? $subActive : '' }}">@if (request()->routeIs('reports.payables')) {!! $dotActive !!} @else {!! $dot !!} @endif{{ __('ui.nav.rep_payables') }}</a>
                        @endif
                        @if ($can('reports.stock'))
                            <a href="{{ route('reports.stock') }}" class="{{ $sub }} {{ request()->routeIs('reports.stock') ? $subActive : '' }}">@if (request()->routeIs('reports.stock')) {!! $dotActive !!} @else {!! $dot !!} @endif{{ __('ui.nav.stock') }}</a>
                        @endif
                        @if ($can('reports.sales'))
                            <a href="{{ route('reports.analytics') }}" class="{{ $sub }} {{ request()->routeIs('reports.analytics') ? $subActive : '' }}">@if (request()->routeIs('reports.analytics')) {!! $dotActive !!} @else {!! $dot !!} @endif{{ __('ui.nav.analytics') }}</a>
                        @endif
                        @if ($can('reports.sales'))
                            <a href="{{ route('reports.insurance-usages') }}" class="{{ $sub }} {{ request()->routeIs('reports.insurance-usages') ? $subActive : '' }}">@if (request()->routeIs('reports.insurance-usages')) {!! $dotActive !!} @else {!! $dot !!} @endif{{ __('ui.nav.insurance_usages') }}</a>
                        @endif
                    </div>
                </div>
            @endif
            @if ($can('reports.financial'))
                @php $act = request()->routeIs('financial.*'); @endphp
                <div x-data="{ open: {{ $act ? 'true' : 'false' }} }">
                    <button type="button" @click="open = !open" class="{{ $item }} {{ $act ? $itemActive : '' }}">
                        @if ($act) {!! $goldBar !!} @endif
                        <svg class="{{ $ico }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="m7 14 4-4 3 3 5-6"/></svg>
                        {{ __('ui.nav.reports_finance') }}
                        <svg :class="open ? 'rotate-180' : ''" class="{{ $chev }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                    </button>
                    <div x-show="open" @if (! $act) style="display: none;" @endif class="mt-0.5 space-y-0.5">
                        <a href="{{ route('financial.balance-sheet') }}" class="{{ $sub }} {{ request()->routeIs('financial.balance-sheet') ? $subActive : '' }}">@if (request()->routeIs('financial.balance-sheet')) {!! $dotActive !!} @else {!! $dot !!} @endif{{ __('ui.nav.fin_balance') }}</a>
                        <a href="{{ route('financial.income') }}" class="{{ $sub }} {{ request()->routeIs('financial.income') ? $subActive : '' }}">@if (request()->routeIs('financial.income')) {!! $dotActive !!} @else {!! $dot !!} @endif{{ __('ui.nav.fin_income') }}</a>
                        <a href="{{ route('financial.cash-flow') }}" class="{{ $sub }} {{ request()->routeIs('financial.cash-flow') ? $subActive : '' }}">@if (request()->routeIs('financial.cash-flow')) {!! $dotActive !!} @else {!! $dot !!} @endif{{ __('ui.nav.fin_cashflow') }}</a>
                    </div>
                </div>
            @endif
        @endif

        {{-- KOMUNIKASI --}}
        @if ($can('wa.send') || $can('wa.templates') || $can('wa.inbox') || $can('settings.manage'))
            <p class="px-3 pb-1.5 pt-5 text-[11px] font-bold uppercase tracking-[0.08em] text-lentera-300/70">{{ __('ui.nav.communication') }}</p>
            @php $act = request()->routeIs('wa-*'); @endphp
            <div x-data="{ open: {{ $act ? 'true' : 'false' }} }">
                <button type="button" @click="open = !open" class="{{ $item }} {{ $act ? $itemActive : '' }}">
                    @if ($act) {!! $goldBar !!} @endif
                    <svg class="{{ $ico }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 11.5a8.5 8.5 0 0 1-12.4 7.6L3 21l1.9-5.6A8.5 8.5 0 1 1 21 11.5Z"/></svg>
                    {{ __('ui.nav.whatsapp') }}
                    <svg :class="open ? 'rotate-180' : ''" class="{{ $chev }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                </button>
                <div x-show="open" @if (! $act) style="display: none;" @endif class="mt-0.5 space-y-0.5">
                    @if ($can('wa.inbox'))
                        <a href="{{ route('wa-inbox.index') }}" class="{{ $sub }} {{ request()->routeIs('wa-inbox.*') ? $subActive : '' }}">@if (request()->routeIs('wa-inbox.*')) {!! $dotActive !!} @else {!! $dot !!} @endif{{ __('ui.nav.wa_inbox') }}</a>
                    @endif
                    @if ($can('wa.campaigns'))
                        <a href="{{ route('wa-campaigns.index') }}" class="{{ $sub }} {{ request()->routeIs('wa-campaigns.*') ? $subActive : '' }}">@if (request()->routeIs('wa-campaigns.*')) {!! $dotActive !!} @else {!! $dot !!} @endif{{ __('ui.nav.wa_campaigns') }}</a>
                    @endif
                    @if ($can('wa.send'))
                        <a href="{{ route('wa-log.index') }}" class="{{ $sub }} {{ request()->routeIs('wa-log.*') ? $subActive : '' }}">@if (request()->routeIs('wa-log.*')) {!! $dotActive !!} @else {!! $dot !!} @endif{{ __('ui.nav.wa_log') }}</a>
                    @endif
                    @if ($can('wa.templates'))
                        <a href="{{ route('wa-templates.index') }}" class="{{ $sub }} {{ request()->routeIs('wa-templates.*') ? $subActive : '' }}">@if (request()->routeIs('wa-templates.*')) {!! $dotActive !!} @else {!! $dot !!} @endif{{ __('ui.nav.wa_templates') }}</a>
                    @endif
                    @if ($can('settings.manage'))
                        <a href="{{ route('wa-gateway.edit') }}" class="{{ $sub }} {{ request()->routeIs('wa-gateway.*') ? $subActive : '' }}">@if (request()->routeIs('wa-gateway.*')) {!! $dotActive !!} @else {!! $dot !!} @endif{{ __('ui.nav.wa_gateway') }}</a>
                        <a href="{{ route('ai-setting.edit') }}" class="{{ $sub }} {{ request()->routeIs('ai-setting.*') ? $subActive : '' }}">@if (request()->routeIs('ai-setting.*')) {!! $dotActive !!} @else {!! $dot !!} @endif{{ __('ui.nav.ai_settings') }}</a>
                        <a href="{{ route('audit-logs.index') }}" class="{{ $sub }} {{ request()->routeIs('audit-logs.*') ? $subActive : '' }}">@if (request()->routeIs('audit-logs.*')) {!! $dotActive !!} @else {!! $dot !!} @endif{{ __('ui.nav.audit_logs') }}</a>
                        <a href="{{ route('backups.index') }}" class="{{ $sub }} {{ request()->routeIs('backups.*') ? $subActive : '' }}">@if (request()->routeIs('backups.*')) {!! $dotActive !!} @else {!! $dot !!} @endif{{ __('ui.nav.backups') }}</a>
                    @endif
                    @if ($can('api_tokens.manage'))
                        <a href="{{ route('api-tokens.index') }}" class="{{ $sub }} {{ request()->routeIs('api-tokens.*') ? $subActive : '' }}">@if (request()->routeIs('api-tokens.*')) {!! $dotActive !!} @else {!! $dot !!} @endif{{ __('ui.nav.api_tokens') }}</a>
                        <a href="{{ route('offline-devices.index') }}" class="{{ $sub }} {{ request()->routeIs('offline-devices.*') ? $subActive : '' }}">@if (request()->routeIs('offline-devices.*')) {!! $dotActive !!} @else {!! $dot !!} @endif{{ __('ui.nav.offline_devices') }}</a>
                    @endif
                </div>
            </div>
        @endif

        {{-- MASTER & PENGATURAN --}}
        @if ($can('products.view') || $can('users.manage') || $can('roles.manage') || $can('branches.manage') || $can('insurances.manage'))
            <p class="px-3 pb-1.5 pt-5 text-[11px] font-bold uppercase tracking-[0.08em] text-lentera-300/70">{{ __('ui.nav.group_master_settings') }}</p>
            @if ($can('products.view'))
                @php $act = request()->routeIs('products.*'); @endphp
                <div x-data="{ open: {{ $act ? 'true' : 'false' }} }">
                    <button type="button" @click="open = !open" class="{{ $item }} {{ $act ? $itemActive : '' }}">
                        @if ($act) {!! $goldBar !!} @endif
                        <svg class="{{ $ico }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m12 2 9 5-9 5-9-5 9-5Z"/><path d="m3 12 9 5 9-5"/><path d="m3 17 9 5 9-5"/></svg>
                        {{ __('ui.nav.catalog') }}
                        <svg :class="open ? 'rotate-180' : ''" class="{{ $chev }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                    </button>
                    <div x-show="open" @if (! $act) style="display: none;" @endif class="mt-0.5 space-y-0.5">
                        @foreach (\App\Modules\Catalog\Models\Product::TYPES as $t)
                            <a href="{{ route('products.index', $t) }}" class="{{ $sub }} {{ request()->routeIs('products.index') && request()->route('type') === $t ? $subActive : '' }}">@if (request()->routeIs('products.index') && request()->route('type') === $t) {!! $dotActive !!} @else {!! $dot !!} @endif{{ __('ui.products.types.'.$t) }}</a>
                        @endforeach
                    </div>
                </div>
            @endif
            @if ($can('users.manage'))
                <a href="{{ route('users.index') }}" class="{{ $item }} {{ request()->routeIs('users.*') ? $itemActive : '' }}">
                    @if (request()->routeIs('users.*')) {!! $goldBar !!} @endif
                    <svg class="{{ $ico }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 4.6a3.5 3.5 0 0 1 0 6.8"/><path d="M17.5 14.4a6.5 6.5 0 0 1 4 5.6"/></svg>
                    {{ __('ui.nav.users') }}
                </a>
            @endif
            @if ($can('roles.manage'))
                <a href="{{ route('roles.index') }}" class="{{ $item }} {{ request()->routeIs('roles.*') ? $itemActive : '' }}">
                    @if (request()->routeIs('roles.*')) {!! $goldBar !!} @endif
                    <svg class="{{ $ico }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3 4 6v6c0 4.5 3.4 7.7 8 9 4.6-1.3 8-4.5 8-9V6l-8-3Z"/><path d="m9 12 2 2 4-4"/></svg>
                    {{ __('ui.nav.roles') }}
                </a>
            @endif
            @if ($can('branches.manage'))
                <a href="{{ route('branches.index') }}" class="{{ $item }} {{ request()->routeIs('branches.*') ? $itemActive : '' }}">
                    @if (request()->routeIs('branches.*')) {!! $goldBar !!} @endif
                    <svg class="{{ $ico }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18"/><path d="M5 21V8l7-5 7 5v13"/><path d="M10 21v-4h4v4"/></svg>
                    {{ __('ui.nav.branches') }}
                </a>
            @endif
            @if ($can('insurances.manage'))
                <a href="{{ route('insurances.index') }}" class="{{ $item }} {{ request()->routeIs('insurances.*') ? $itemActive : '' }}">
                    @if (request()->routeIs('insurances.*')) {!! $goldBar !!} @endif
                    <svg class="{{ $ico }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3 4 6v6c0 4.5 3.4 7.7 8 9 4.6-1.3 8-4.5 8-9V6l-8-3Z"/></svg>
                    {{ __('ui.nav.insurances') }}
                </a>
            @endif
        @endif
    </nav>

    {{-- Pengguna aktif --}}
    <div class="flex shrink-0 items-center gap-2.5 border-t border-white/10 px-5 py-3.5">
        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-lentera-800 text-xs font-bold text-white ring-1 ring-gold/50">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($u->name, 0, 1)) }}</span>
        <span class="min-w-0">
            <span class="block truncate text-[13px] font-semibold leading-tight text-white">{{ $u->name }}</span>
            <span class="block text-[11px] leading-tight text-lentera-300/70">{{ $u->roles->first()?->label ?? '' }}</span>
        </span>
    </div>
</aside>
