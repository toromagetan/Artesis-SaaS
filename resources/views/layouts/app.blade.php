<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Lentera') }}</title>

        <!-- Fonts: Plus Jakarta Sans (token tipografi F.2.0) -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen">
            {{-- VARIAN A (terkunci, Blueprint F.2 butir 9): satu header ramping —
                 baris atas = merek + navigasi + GlobalSearch + bahasa + pengguna;
                 baris judul = judul halaman TEPAT SATU KALI. Body tidak mengulang judul. --}}
            <header class="bg-white shadow-soft">
                @include('layouts.navigation')

                @isset($header)
                    <div class="border-t border-gray-100">
                        <div class="mx-auto max-w-7xl px-4 py-3 sm:px-6 lg:px-8">
                            {{ $header }}
                        </div>
                    </div>
                @endisset
            </header>

            <!-- Page Content -->
            <main>
                {{ $slot }}
            </main>
        </div>
    </body>
</html>
