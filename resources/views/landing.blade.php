<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Artesis — {{ __('ui.nav.tagline') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-lentera-50 font-sans text-gray-800 antialiased">
    <div class="mx-auto flex min-h-screen max-w-5xl flex-col px-4">
        <header class="flex items-center justify-between py-5">
            <div class="flex items-center gap-3">
                <span class="relative flex h-10 w-10 items-center justify-center rounded-xl bg-lentera-900 ring-2 ring-gold/70">
                    <span class="h-2.5 w-2.5 rounded-full bg-gold"></span>
                </span>
                <span>
                    <span class="block text-lg font-bold leading-tight text-lentera-950">Artesis</span>
                    <span class="block text-[11px] leading-tight text-gray-500">{{ __('ui.nav.tagline') }}</span>
                </span>
            </div>
            <div class="flex items-center gap-2 text-sm">
                <form method="POST" action="{{ route('locale.switch', 'id') }}">
                    @csrf
                    <button type="submit" class="rounded-lg px-2.5 py-1.5 font-medium {{ app()->getLocale() === 'id' ? 'bg-lentera-100 text-lentera-900' : 'text-gray-500 hover:bg-white' }}">Bahasa Indonesia</button>
                </form>
                <form method="POST" action="{{ route('locale.switch', 'en') }}">
                    @csrf
                    <button type="submit" class="rounded-lg px-2.5 py-1.5 font-medium {{ app()->getLocale() === 'en' ? 'bg-lentera-100 text-lentera-900' : 'text-gray-500 hover:bg-white' }}">English</button>
                </form>
                <form method="POST" action="{{ route('locale.switch', 'ms') }}">
                    @csrf
                    <button type="submit" class="rounded-lg px-2.5 py-1.5 font-medium {{ app()->getLocale() === 'ms' ? 'bg-lentera-100 text-lentera-900' : 'text-gray-500 hover:bg-white' }}">Bahasa Melayu</button>
                </form>
                <form method="POST" action="{{ route('locale.switch', 'zh') }}">
                    @csrf
                    <button type="submit" class="rounded-lg px-2.5 py-1.5 font-medium {{ app()->getLocale() === 'zh' ? 'bg-lentera-100 text-lentera-900' : 'text-gray-500 hover:bg-white' }}">中文</button>
                </form>
            </div>
        </header>

        <main class="flex flex-1 flex-col items-center justify-center py-10 text-center">
            <h1 class="max-w-2xl text-4xl font-bold leading-tight text-lentera-950 sm:text-5xl">{{ __('ui.nav.tagline') }}</h1>
            <p class="mt-5 max-w-xl text-base leading-relaxed text-gray-600">{{ __('ui.landing.description') }}</p>
            <div class="mt-8 flex flex-col items-center gap-3">
                <a href="{{ route('login') }}" class="inline-flex items-center justify-center rounded-lg bg-lentera-800 px-6 py-3 text-sm font-semibold text-white shadow-soft transition hover:bg-lentera-700 focus:outline-none focus:ring-2 focus:ring-lentera-500">{{ __('ui.landing.login') }}</a>
                <p class="text-xs text-gray-500">{{ __('ui.landing.internal_note') }}</p>
            </div>

            <div class="mt-14 grid w-full gap-4 text-left sm:grid-cols-3">
                <div class="rounded-2xl bg-white p-5 shadow-soft ring-1 ring-lentera-100">
                    <h2 class="text-sm font-semibold text-lentera-900">{{ __('ui.landing.f1_title') }}</h2>
                    <p class="mt-1.5 text-sm leading-relaxed text-gray-600">{{ __('ui.landing.f1_body') }}</p>
                </div>
                <div class="rounded-2xl bg-white p-5 shadow-soft ring-1 ring-lentera-100">
                    <h2 class="text-sm font-semibold text-lentera-900">{{ __('ui.landing.f2_title') }}</h2>
                    <p class="mt-1.5 text-sm leading-relaxed text-gray-600">{{ __('ui.landing.f2_body') }}</p>
                </div>
                <div class="rounded-2xl bg-white p-5 shadow-soft ring-1 ring-lentera-100">
                    <h2 class="text-sm font-semibold text-lentera-900">{{ __('ui.landing.f3_title') }}</h2>
                    <p class="mt-1.5 text-sm leading-relaxed text-gray-600">{{ __('ui.landing.f3_body') }}</p>
                </div>
            </div>
        </main>

        <footer class="py-6 text-center text-xs text-gray-400">{{ __('ui.landing.footer') }}</footer>
    </div>
</body>
</html>
