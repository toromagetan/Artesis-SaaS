<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Artesis') }}</title>

        <!-- Fonts: Plus Jakarta Sans (token tipografi F.2.0) -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        {{-- SIDEBAR (keputusan owner 2026-10-18, ADR-013 — menggantikan Varian A):
             sidebar kiri = navigasi terklasifikasi 6 kelompok; topbar ramping =
             judul halaman TEPAT SATU KALI + GlobalSearch selalu di atas +
             bahasa + pengguna. Body tidak mengulang judul. --}}
        <div class="min-h-screen lg:flex" x-data="{ sidebar: false }">
            @include('layouts.sidebar')

            <div class="flex min-h-screen min-w-0 flex-1 flex-col">
                <header class="sticky top-0 z-20 bg-white shadow-soft">
                    <div class="flex h-16 items-center gap-3 px-4 sm:px-6 lg:px-8">
                        <button type="button" @click="sidebar = true" class="inline-flex shrink-0 items-center justify-center rounded-lg border border-gray-200 p-2 text-gray-500 transition duration-150 hover:text-lentera-800 lg:hidden" aria-label="{{ __('ui.open_menu') }}">
                            <svg class="h-5 w-5" stroke="currentColor" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
                        </button>

                        @isset($header)
                            <div class="min-w-0 shrink-0">{{ $header }}</div>
                        @endisset

                        <!-- Global Search (selalu di topbar) -->
                        <div class="mx-auto hidden w-full max-w-md md:block">
                            <x-lentera.global-search />
                        </div>

                        <div class="ml-auto flex shrink-0 items-center gap-1 md:ml-0">
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
                                    <form method="POST" action="{{ route('locale.switch', 'ms') }}">
                                        @csrf
                                        <x-dropdown-link :href="route('locale.switch', 'ms')" onclick="event.preventDefault(); this.closest('form').submit();">Bahasa Melayu</x-dropdown-link>
                                    </form>
                                    <form method="POST" action="{{ route('locale.switch', 'zh') }}">
                                        @csrf
                                        <x-dropdown-link :href="route('locale.switch', 'zh')" onclick="event.preventDefault(); this.closest('form').submit();">中文</x-dropdown-link>
                                    </form>
                                </x-slot>
                            </x-dropdown>

                            <!-- User Dropdown -->
                            <x-dropdown align="right" width="48">
                                <x-slot name="trigger">
                                    <button class="inline-flex items-center rounded-lg px-2 py-2 text-sm font-medium text-gray-500 transition duration-150 hover:text-lentera-800 focus:outline-none">
                                        <span class="hidden sm:inline">{{ Auth::user()->name }}</span>
                                        <span class="flex h-7 w-7 items-center justify-center rounded-full bg-lentera-800 text-[11px] font-bold text-white sm:hidden">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr(Auth::user()->name, 0, 1)) }}</span>
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
                        </div>
                    </div>

                    <!-- Global Search baris kedua khusus layar kecil -->
                    <div class="border-t border-gray-100 px-4 py-2 md:hidden">
                        <x-lentera.global-search />
                    </div>
                </header>

                <!-- Page Content -->
                <main class="flex-1">
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
