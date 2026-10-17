<nav x-data="{ open: false }" class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
    <div class="flex h-16 items-center gap-4">
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
        <div class="hidden items-center gap-1 md:flex">
            <a href="{{ route('dashboard') }}"
               class="rounded-lg px-3 py-2 text-sm font-medium transition duration-150 {{ request()->routeIs('dashboard') ? 'bg-lentera-50 text-lentera-800' : 'text-gray-600 hover:bg-gray-50 hover:text-lentera-800' }}">{{ __('ui.nav.dashboard') }}</a>
            @if (Auth::user()->hasPermission('users.manage'))
                <a href="{{ route('users.index') }}"
                   class="rounded-lg px-3 py-2 text-sm font-medium transition duration-150 {{ request()->routeIs('users.*') ? 'bg-lentera-50 text-lentera-800' : 'text-gray-600 hover:bg-gray-50 hover:text-lentera-800' }}">{{ __('ui.nav.users') }}</a>
            @endif
            @if (Auth::user()->hasPermission('roles.manage'))
                <a href="{{ route('roles.index') }}"
                   class="rounded-lg px-3 py-2 text-sm font-medium transition duration-150 {{ request()->routeIs('roles.*') ? 'bg-lentera-50 text-lentera-800' : 'text-gray-600 hover:bg-gray-50 hover:text-lentera-800' }}">{{ __('ui.nav.roles') }}</a>
            @endif
            @if (Auth::user()->hasPermission('branches.manage'))
                <a href="{{ route('branches.index') }}"
                   class="rounded-lg px-3 py-2 text-sm font-medium transition duration-150 {{ request()->routeIs('branches.*') ? 'bg-lentera-50 text-lentera-800' : 'text-gray-600 hover:bg-gray-50 hover:text-lentera-800' }}">{{ __('ui.nav.branches') }}</a>
            @endif
        </div>

        <!-- Global Search (selalu di header) -->
        <div class="mx-auto hidden w-full max-w-md sm:block">
            <x-lentera.global-search />
        </div>

        <div class="ml-auto flex items-center gap-1 sm:ml-0">
            <!-- Language Dropdown (ADR-011) -->
            <x-dropdown align="right" width="48">
                <x-slot name="trigger">
                    <button class="inline-flex items-center rounded-lg px-3 py-2 text-sm font-medium text-gray-500 transition duration-150 hover:text-lentera-800 focus:outline-none">
                        {{ __('ui.language') }}: {{ strtoupper(app()->getLocale()) }}
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
                    <button class="inline-flex items-center rounded-lg px-3 py-2 text-sm font-medium text-gray-500 transition duration-150 hover:text-lentera-800 focus:outline-none">
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
