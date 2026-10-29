<x-guest-layout>
    <h1 class="text-center text-xl font-bold text-lentera-950">{{ __('ui.signup.title') }}</h1>
    <p class="mt-1 text-center text-sm text-gray-600">{{ __('ui.signup.subtitle') }}</p>

    <form method="POST" action="{{ route('signup.store') }}" class="mt-6 space-y-4">
        @csrf

        <div>
            <x-input-label for="business_name" :value="__('ui.signup.business_name')" />
            <x-text-input id="business_name" name="business_name" type="text" class="mt-1 block w-full" :value="old('business_name')" required autofocus />
            <x-input-error :messages="$errors->get('business_name')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="owner_name" :value="__('ui.signup.owner_name')" />
            <x-text-input id="owner_name" name="owner_name" type="text" class="mt-1 block w-full" :value="old('owner_name')" required />
            <x-input-error :messages="$errors->get('owner_name')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="email" :value="__('ui.signup.email')" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email')" required />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="phone" :value="__('ui.signup.phone')" />
            <x-text-input id="phone" name="phone" type="text" class="mt-1 block w-full" :value="old('phone')" />
            <x-input-error :messages="$errors->get('phone')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="branch_name" :value="__('ui.signup.branch_name')" />
            <x-text-input id="branch_name" name="branch_name" type="text" class="mt-1 block w-full" :value="old('branch_name')" required />
            <x-input-error :messages="$errors->get('branch_name')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" :value="__('ui.signup.password')" />
            <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password_confirmation" :value="__('ui.signup.password_confirmation')" />
            <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <x-primary-button class="w-full justify-center">
            {{ __('ui.signup.submit') }}
        </x-primary-button>

        <p class="text-center text-sm text-gray-600">
            {{ __('ui.signup.have_account') }}
            <a href="{{ route('login') }}" class="font-semibold text-lentera-700 hover:underline">{{ __('ui.signup.login') }}</a>
        </p>
    </form>
</x-guest-layout>
