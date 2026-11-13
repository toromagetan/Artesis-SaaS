<x-guest-layout>
    <div class="mb-4">
        <h1 class="text-lg font-semibold text-gray-800">{{ __('ui.auth.verification.title') }}</h1>
        <p class="mt-2 text-sm text-gray-600">
            {{ __('ui.auth.verification.body', ['email' => auth()->user()->email]) }}
        </p>
        <p class="mt-2 text-sm text-gray-600">
            {{ __('ui.auth.verification.waiting') }}
        </p>
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-4 font-medium text-sm text-green-600">
            {{ __('ui.auth.verification.link_sent') }}
        </div>
    @endif

    <div class="mt-4 flex items-center justify-between">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-primary-button>
                {{ __('ui.auth.verification.resend') }}
            </x-primary-button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="underline text-sm text-gray-600 hover:text-gray-900">
                {{ __('ui.auth.logout') }}
            </button>
        </form>
    </div>
</x-guest-layout>
