<x-guest-layout>
    <h1 class="mb-2 text-lg font-semibold text-lentera-950">{{ __('ui.recovery.title') }}</h1>
    <div class="mb-4 text-sm text-gray-600">
        {{ __('ui.recovery.help') }}
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('recovery.email') }}">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="flex items-center justify-between mt-4">
            <a class="text-sm text-gray-600 underline hover:text-lentera-800" href="{{ route('login') }}">{{ __('ui.recovery.back_to_login') }}</a>
            <x-primary-button>
                {{ __('ui.recovery.submit') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
