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
    <body class="font-sans text-gray-900 antialiased">
        <div class="flex min-h-screen flex-col items-center pt-6 sm:justify-center sm:pt-0">
            <a href="/" class="flex items-center gap-2">
                <span class="flex h-10 w-10 items-center justify-center rounded-full bg-lentera-800">
                    <span class="h-3 w-3 rounded-full bg-gold"></span>
                </span>
                <span class="text-2xl font-extrabold tracking-tight text-lentera-950">Artesis</span>
            </a>

            <div class="mt-6 w-full overflow-hidden bg-white px-6 py-4 shadow-soft sm:max-w-md sm:rounded-xl">
                {{ $slot }}
            </div>

            {{-- Pengalih bahasa untuk tamu (ADR-011: preferensi via sesi) --}}
            <div class="mt-4 flex items-center gap-2 text-sm text-gray-500">
                <span>{{ __('ui.language') }}:</span>
                <form method="POST" action="{{ route('locale.switch', 'id') }}">
                    @csrf
                    <button type="submit" class="underline-offset-2 hover:underline {{ app()->getLocale() === 'id' ? 'font-semibold text-lentera-800' : '' }}">Bahasa Indonesia</button>
                </form>
                <span aria-hidden="true">·</span>
                <form method="POST" action="{{ route('locale.switch', 'en') }}">
                    @csrf
                    <button type="submit" class="underline-offset-2 hover:underline {{ app()->getLocale() === 'en' ? 'font-semibold text-lentera-800' : '' }}">English</button>
                </form>
                <span aria-hidden="true">·</span>
                <form method="POST" action="{{ route('locale.switch', 'ms') }}">
                    @csrf
                    <button type="submit" class="underline-offset-2 hover:underline {{ app()->getLocale() === 'ms' ? 'font-semibold text-lentera-800' : '' }}">Bahasa Melayu</button>
                </form>
                <span aria-hidden="true">·</span>
                <form method="POST" action="{{ route('locale.switch', 'zh') }}">
                    @csrf
                    <button type="submit" class="underline-offset-2 hover:underline {{ app()->getLocale() === 'zh' ? 'font-semibold text-lentera-800' : '' }}">中文</button>
                </form>
            </div>
        </div>
    </body>
</html>
