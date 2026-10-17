<nav x-data="{ open: false }" class="mx-auto max-w-[1440px] px-4 sm:px-6 lg:px-8">
    <div class="flex h-16 items-center gap-2">
        <!-- Brand -->
        <div class="flex shrink-0 items-center gap-2">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-2">
                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-lentera-800">
                    <span class="h-2.5 w-2.5 rounded-full bg-gold"></span>
                </span>
                <span class="text-lg font-extrabold tracking-tight text-lentera-950">Lentera</span>
            </a>
        </div>

        <!-- Navigation Links -->
        <div class="hidden min-w-0 flex-nowrap items-center gap-1 overflow-x-auto md:flex [scrollbar-width:none] [&::-webkit-scrollbar]:hidden [&_a]:whitespace-nowrap [&_button]:whitespace-nowrap">
            <a href="{{ route('dashboard') }}"
               class="rounded-lg px-2 py-2 text-sm font-medium transition duration-150 {{ request()->routeIs('dashboard') ? 'bg-lentera-50 text-lentera-800' : 'text-gray-600 hover:bg-gray-50 hover:text-lentera-800' }}">{{ __('ui.nav.dashboard') }}</a>
            @if (Auth::user()->hasPermission('sales.view'))
                <a href="{{ route('sales.index') }}"
                   class="rounded-lg px-2 py-2 text-sm font-medium transition duration-150 {{ request()->routeIs('sales.*') ? 'bg-lentera-50 text-lentera-800' : 'text-gray-600 hover:bg-gray-50 hover:text-lentera-800' }}">{{ __('ui.nav.sales') }}</a>
            @endif
            @if (Auth::user()->hasPermission('customers.view'))
                <a href="{{ route('customers.index') }}"
                   class="rounded-lg px-2 py-2 text-sm font-medium transition duration-150 {{ request()->routeIs('customers.*') || request()->routeIs('prescriptions.*') ? 'bg-lentera-50 text-lentera-800' : 'text-gray-600 hover:bg-gray-50 hover:text-lentera-800' }}">{{ __('ui.nav.customers') }}</a>
            @endif
            @if (Auth::user()->hasPermission('pr.view') || Auth::user()->hasPermission('po.view'))
                <x-dropdown align="left" width="56">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center rounded-lg px-2 py-2 text-sm font-medium transition duration-150 {{ request()->routeIs('pr.*') || request()->routeIs('po.*') || request()->routeIs('receipts.*') || request()->routeIs('payments.*') ? 'bg-lentera-50 text-lentera-800' : 'text-gray-600 hover:bg-gray-50 hover:text-lentera-800' }}">
                            {{ __('ui.nav.purchasing') }}
                            <svg class="ms-1 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" /></svg>
                        </button>
                    </x-slot>
                    <x-slot name="content">
                        @if (Auth::user()->hasPermission('pr.view'))
                            <x-dropdown-link :href="route('pr.index')">{{ __('ui.purchasing.pr_title') }}</x-dropdown-link>
                        @endif
                        @if (Auth::user()->hasPermission('po.view'))
                            <x-dropdown-link :href="route('po.index')">{{ __('ui.purchasing.po_title') }}</x-dropdown-link>
                        @endif
                        @if (Auth::user()->hasPermission('payments.collective'))
                            <x-dropdown-link :href="route('payments.collective')">{{ __('ui.purchasing.collective') }}</x-dropdown-link>
                        @endif
                    </x-slot>
                </x-dropdown>
            @endif
            @if (Auth::user()->hasPermission('lab.view'))
                <a href="{{ route('lab.index') }}"
                   class="rounded-lg px-2 py-2 text-sm font-medium transition duration-150 {{ request()->routeIs('lab.*') ? 'bg-lentera-50 text-lentera-800' : 'text-gray-600 hover:bg-gray-50 hover:text-lentera-800' }}">{{ __('ui.nav.lab') }}</a>
            @endif
            @if (Auth::user()->hasPermission('stock.view'))
                <x-dropdown align="left" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center rounded-lg px-2 py-2 text-sm font-medium transition duration-150 {{ request()->routeIs('stock.*') || request()->routeIs('transfers.*') ? 'bg-lentera-50 text-lentera-800' : 'text-gray-600 hover:bg-gray-50 hover:text-lentera-800' }}">
                            {{ __('ui.nav.stock') }}
                            <svg class="ms-1 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" /></svg>
                        </button>
                    </x-slot>
                    <x-slot name="content">
                        <x-dropdown-link :href="route('stock.index')">{{ __('ui.nav.stock') }}</x-dropdown-link>
                        <x-dropdown-link :href="route('transfers.index')">{{ __('ui.nav.transfers') }}</x-dropdown-link>
                        @if (Auth::user()->can('stock.import-initial'))
                            <x-dropdown-link :href="route('stock.import.form')">{{ __('ui.import.title') }}</x-dropdown-link>
                        @endif
                    </x-slot>
                </x-dropdown>
            @endif
            @if (Auth::user()->hasPermission('reports.sales') || Auth::user()->hasPermission('reports.purchases') || Auth::user()->hasPermission('reports.stock') || Auth::user()->hasPermission('reports.financial'))
                <a href="{{ route('reports.sales') }}"
                   class="rounded-lg px-2 py-2 text-sm font-medium transition duration-150 {{ request()->routeIs('reports.*') ? 'bg-lentera-50 text-lentera-800' : 'text-gray-600 hover:bg-gray-50 hover:text-lentera-800' }}">{{ __('ui.nav.reports') }}</a>
            @endif
            @if (Auth::user()->hasPermission('journals.view') || Auth::user()->hasPermission('assets.manage') || Auth::user()->hasPermission('periods.lock') || Auth::user()->hasPermission('payroll.manage'))
                <x-dropdown align="left" width="56">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center rounded-lg px-2 py-2 text-sm font-medium transition duration-150 {{ request()->routeIs('journals.*') || request()->routeIs('ledger.*') || request()->routeIs('accounts.*') || request()->routeIs('assets.*') || request()->routeIs('periods.*') || request()->routeIs('payroll.*') || request()->routeIs('commissions.*') ? 'bg-lentera-50 text-lentera-800' : 'text-gray-600 hover:bg-gray-50 hover:text-lentera-800' }}">
                            {{ __('ui.nav.accounting') }}
                            <svg class="ms-1 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" /></svg>
                        </button>
                    </x-slot>
                    <x-slot name="content">
                        @if (Auth::user()->hasPermission('journals.view'))
                            <x-dropdown-link :href="route('journals.index')">{{ __('ui.nav.journals') }}</x-dropdown-link>
                            <x-dropdown-link :href="route('ledger.index')">{{ __('ui.nav.ledger') }}</x-dropdown-link>
                        @endif
                        @if (Auth::user()->hasPermission('accounts.view'))
                            <x-dropdown-link :href="route('accounts.index')">{{ __('ui.accounting.accounts') }}</x-dropdown-link>
                        @endif
                        @if (Auth::user()->hasPermission('assets.manage'))
                            <x-dropdown-link :href="route('assets.index')">{{ __('ui.nav.assets') }}</x-dropdown-link>
                        @endif
                        @if (Auth::user()->hasPermission('periods.lock'))
                            <x-dropdown-link :href="route('periods.index')">{{ __('ui.nav.periods') }}</x-dropdown-link>
                        @endif
                        @if (Auth::user()->hasPermission('payroll.manage'))
                            <x-dropdown-link :href="route('payroll.index')">{{ __('ui.nav.payroll') }}</x-dropdown-link>
                        @endif
                    </x-slot>
                </x-dropdown>
            @endif
            @if (Auth::user()->hasPermission('users.manage') || Auth::user()->hasPermission('roles.manage') || Auth::user()->hasPermission('branches.manage'))
                <x-dropdown align="left" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center rounded-lg px-2 py-2 text-sm font-medium transition duration-150 {{ request()->routeIs('users.*') || request()->routeIs('roles.*') || request()->routeIs('branches.*') ? 'bg-lentera-50 text-lentera-800' : 'text-gray-600 hover:bg-gray-50 hover:text-lentera-800' }}">
                            {{ __('ui.nav.settings') }}
                            <svg class="ms-1 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" /></svg>
                        </button>
                    </x-slot>
                    <x-slot name="content">
                        @if (Auth::user()->hasPermission('users.manage'))
                            <x-dropdown-link :href="route('users.index')">{{ __('ui.nav.users') }}</x-dropdown-link>
                        @endif
                        @if (Auth::user()->hasPermission('roles.manage'))
                            <x-dropdown-link :href="route('roles.index')">{{ __('ui.nav.roles') }}</x-dropdown-link>
                        @endif
                        @if (Auth::user()->hasPermission('branches.manage'))
                            <x-dropdown-link :href="route('branches.index')">{{ __('ui.nav.branches') }}</x-dropdown-link>
                        @endif
                    </x-slot>
                </x-dropdown>
            @endif
            @if (Auth::user()->hasPermission('products.view'))
                <x-dropdown align="left" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center rounded-lg px-2 py-2 text-sm font-medium transition duration-150 {{ request()->routeIs('products.*') ? 'bg-lentera-50 text-lentera-800' : 'text-gray-600 hover:bg-gray-50 hover:text-lentera-800' }} focus:outline-none">
                            {{ __('ui.nav.catalog') }}
                            <svg class="ms-1 h-4 w-4 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                        </button>
                    </x-slot>
                    <x-slot name="content">
                        @foreach (\App\Modules\Catalog\Models\Product::TYPES as $t)
                            <x-dropdown-link :href="route('products.index', $t)">{{ __('ui.products.types.'.$t) }}</x-dropdown-link>
                        @endforeach
                    </x-slot>
                </x-dropdown>
            @endif
        </div>

        <!-- Global Search (selalu di header) -->
        <div class="mx-auto hidden w-40 shrink-0 sm:block">
            <x-lentera.global-search />
        </div>

        <div class="ml-auto flex items-center gap-1 sm:ml-0">
            <!-- Language Dropdown (ADR-011) -->
            <x-dropdown align="right" width="48">
                <x-slot name="trigger">
                    <button class="inline-flex items-center rounded-lg px-2 py-2 text-sm font-medium text-gray-500 transition duration-150 hover:text-lentera-800 focus:outline-none">
                        {{ strtoupper(app()->getLocale()) }}
                        <svg class="ms-1 h-4 w-4 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                    </button>
                </x-slot>
                <x-slot name="content">
                    <form method="POST" action="{{ route('locale.switch', 'id') }}">
                        @csrf
                        <x-dropdown-link :href="route('locale.switch', 'id')" onclick="event.preventDefault(); this.closest('form').submit();">Bahasa Indonesia</x-dropdown-link>
                    </form>
                    <form method="POST" action="{{ route('locale.switch', 'en') }}">
                        @csrf
                        <x-dropdown-link :href="route('locale.switch', 'en')" onclick="event.preventDefault(); this.closest('form').submit();">English</x-dropdown-link>
                    </form>
                </x-slot>
            </x-dropdown>

            <!-- User Dropdown -->
            <x-dropdown align="right" width="48">
                <x-slot name="trigger">
                    <button class="inline-flex items-center rounded-lg px-2 py-2 text-sm font-medium text-gray-500 transition duration-150 hover:text-lentera-800 focus:outline-none">
                        {{ Auth::user()->name }}
                        <svg class="ms-1 h-4 w-4 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                    </button>
                </x-slot>
                <x-slot name="content">
                    <x-dropdown-link :href="route('profile.edit')">{{ __('ui.nav.profile') }}</x-dropdown-link>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <x-dropdown-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">{{ __('ui.nav.logout') }}</x-dropdown-link>
                    </form>
                </x-slot>
            </x-dropdown>

            <!-- Hamburger -->
            <button @click="open = !open" class="inline-flex items-center justify-center rounded-lg p-2 text-gray-400 transition duration-150 hover:bg-gray-50 hover:text-gray-600 focus:outline-none md:hidden">
                <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                    <path :class="{ 'hidden': open, 'inline-flex': !open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    <path :class="{ 'hidden': !open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </div>

    <!-- Mobile: search + links -->
    <div :class="{ 'block': open, 'hidden': !open }" class="hidden pb-4 md:hidden">
        <div class="pb-3 sm:hidden">
            <x-lentera.global-search />
        </div>
        <div class="space-y-1">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">{{ __('ui.nav.dashboard') }}</x-responsive-nav-link>
            @if (Auth::user()->hasPermission('pr.view'))
                <x-responsive-nav-link :href="route('pr.index')" :active="request()->routeIs('pr.*')">{{ __('ui.purchasing.pr_title') }}</x-responsive-nav-link>
            @endif
            @if (Auth::user()->hasPermission('po.view'))
                <x-responsive-nav-link :href="route('po.index')" :active="request()->routeIs('po.*')">{{ __('ui.purchasing.po_title') }}</x-responsive-nav-link>
            @endif
            @if (Auth::user()->hasPermission('payments.collective'))
                <x-responsive-nav-link :href="route('payments.collective')" :active="request()->routeIs('payments.*')">{{ __('ui.purchasing.collective') }}</x-responsive-nav-link>
            @endif
            @if (Auth::user()->hasPermission('lab.view'))
                <x-responsive-nav-link :href="route('lab.index')" :active="request()->routeIs('lab.*')">{{ __('ui.nav.lab') }}</x-responsive-nav-link>
            @endif
            @if (Auth::user()->hasPermission('stock.view'))
                <x-responsive-nav-link :href="route('stock.index')" :active="request()->routeIs('stock.*')">{{ __('ui.nav.stock') }}</x-responsive-nav-link>
            @endif
            @if (Auth::user()->hasPermission('stock.view'))
                <x-responsive-nav-link :href="route('transfers.index')" :active="request()->routeIs('transfers.*')">{{ __('ui.nav.transfers') }}</x-responsive-nav-link>
            @endif
            @if (Auth::user()->hasPermission('journals.view'))
                <x-responsive-nav-link :href="route('journals.index')" :active="request()->routeIs('journals.*')">{{ __('ui.nav.journals') }}</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('ledger.index')" :active="request()->routeIs('ledger.*')">{{ __('ui.nav.ledger') }}</x-responsive-nav-link>
            @endif
            @if (Auth::user()->hasPermission('assets.manage'))
                <x-responsive-nav-link :href="route('assets.index')" :active="request()->routeIs('assets.*')">{{ __('ui.nav.assets') }}</x-responsive-nav-link>
            @endif
            @if (Auth::user()->hasPermission('periods.lock'))
                <x-responsive-nav-link :href="route('periods.index')" :active="request()->routeIs('periods.*')">{{ __('ui.nav.periods') }}</x-responsive-nav-link>
            @endif
            @if (Auth::user()->hasPermission('payroll.manage'))
                <x-responsive-nav-link :href="route('payroll.index')" :active="request()->routeIs('payroll.*') || request()->routeIs('commissions.*')">{{ __('ui.nav.payroll') }}</x-responsive-nav-link>
            @endif
            @if (Auth::user()->hasPermission('reports.sales') || Auth::user()->hasPermission('reports.purchases') || Auth::user()->hasPermission('reports.stock') || Auth::user()->hasPermission('reports.financial'))
                <x-responsive-nav-link :href="route('reports.sales')" :active="request()->routeIs('reports.*')">{{ __('ui.nav.reports') }}</x-responsive-nav-link>
            @endif
            @if (Auth::user()->hasPermission('sales.view'))
                <x-responsive-nav-link :href="route('sales.index')" :active="request()->routeIs('sales.*')">{{ __('ui.nav.sales') }}</x-responsive-nav-link>
            @endif
            @if (Auth::user()->hasPermission('customers.view'))
                <x-responsive-nav-link :href="route('customers.index')" :active="request()->routeIs('customers.*')">{{ __('ui.nav.customers') }}</x-responsive-nav-link>
            @endif
            @if (Auth::user()->hasPermission('products.view'))
                @foreach (\App\Modules\Catalog\Models\Product::TYPES as $t)
                    <x-responsive-nav-link :href="route('products.index', $t)" :active="request()->routeIs('products.*')">{{ __('ui.nav.catalog') }} — {{ __('ui.products.types.'.$t) }}</x-responsive-nav-link>
                @endforeach
            @endif
            @if (Auth::user()->hasPermission('users.manage'))
                <x-responsive-nav-link :href="route('users.index')" :active="request()->routeIs('users.*')">{{ __('ui.nav.users') }}</x-responsive-nav-link>
            @endif
            @if (Auth::user()->hasPermission('roles.manage'))
                <x-responsive-nav-link :href="route('roles.index')" :active="request()->routeIs('roles.*')">{{ __('ui.nav.roles') }}</x-responsive-nav-link>
            @endif
            @if (Auth::user()->hasPermission('branches.manage'))
                <x-responsive-nav-link :href="route('branches.index')" :active="request()->routeIs('branches.*')">{{ __('ui.nav.branches') }}</x-responsive-nav-link>
            @endif
            <x-responsive-nav-link :href="route('profile.edit')">{{ __('ui.nav.profile') }}</x-responsive-nav-link>
        </div>
    </div>
</nav>
