<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.wa.gateway_title') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-lg border border-lentera-200 bg-lentera-50 px-4 py-3 text-sm text-lentera-800">{{ session('status') }}</div>
            @endif
            @if (session('test_result'))
                <div class="mb-4 rounded-lg border border-gold/50 bg-gold/10 px-4 py-3 text-sm text-gold-dark">{{ session('test_result') }}</div>
            @endif
            @if ($errors->any())
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            <div class="mb-6 rounded-lg bg-white p-6 shadow-soft">
                <p class="mb-1 text-sm text-gray-600">{{ __('ui.wa.gateway_intro') }}</p>
                <p class="mb-4 text-sm">
                    {{ __('ui.wa.connection_status') }}:
                    @if ($gateway && $gateway->is_active)
                        <span class="font-medium text-lentera-800">{{ __('ui.wa.status_active') }}</span>
                    @else
                        <span class="font-medium text-gray-500">{{ __('ui.wa.status_inactive') }}</span>
                    @endif
                </p>
                <form method="POST" action="{{ route('wa-gateway.update') }}">
                    @csrf
                    @method('PUT')
                    <div class="grid gap-4">
                        <div>
                            <x-input-label for="provider" :value="__('ui.wa.provider')" />
                            <select id="provider" name="provider" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                                <option value="meta_cloud_api" @selected(old('provider', $gateway?->provider ?? 'meta_cloud_api') === 'meta_cloud_api')>Meta Cloud API ({{ __('ui.wa.provider_direct') }})</option>
                            </select>
                        </div>
                        <div>
                            <x-input-label for="sender_number" :value="__('ui.wa.sender_number')" />
                            <x-text-input id="sender_number" name="sender_number" class="mt-1 block w-full" :value="old('sender_number', $gateway?->sender_number)" required />
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <x-input-label for="phone_number_id" :value="__('ui.wa.phone_number_id')" />
                                <x-text-input id="phone_number_id" name="phone_number_id" class="mt-1 block w-full" :value="old('phone_number_id', $gateway?->credential('phone_number_id'))" required />
                            </div>
                            <div>
                                <x-input-label for="waba_id" :value="__('ui.wa.waba_id')" />
                                <x-text-input id="waba_id" name="waba_id" class="mt-1 block w-full" :value="old('waba_id', $gateway?->credential('waba_id'))" />
                            </div>
                        </div>
                        <div>
                            <x-input-label for="access_token" :value="__('ui.wa.access_token')" />
                            <x-text-input id="access_token" name="access_token" type="password" class="mt-1 block w-full" autocomplete="new-password" :placeholder="$gateway?->maskedToken() ?: ''" />
                            <p class="mt-1 text-xs text-gray-500">{{ __('ui.wa.token_hint') }}</p>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <x-input-label for="verify_token" :value="__('ui.wa.verify_token')" />
                                <x-text-input id="verify_token" name="verify_token" type="password" class="mt-1 block w-full" autocomplete="new-password" :placeholder="$gateway?->credential('verify_token') ? '••••' : ''" />
                            </div>
                            <div>
                                <x-input-label for="api_version" :value="__('ui.wa.api_version')" />
                                <x-text-input id="api_version" name="api_version" class="mt-1 block w-full" :value="old('api_version', $gateway?->credential('api_version', 'v21.0'))" />
                            </div>
                        </div>
                        <div>
                            <x-input-label for="app_secret" :value="__('ui.wa.app_secret')" />
                            <x-text-input id="app_secret" name="app_secret" type="password" class="mt-1 block w-full" autocomplete="new-password" :placeholder="$gateway?->credential('app_secret') ? '••••' : ''" />
                            <p class="mt-1 text-xs text-gray-500">{{ __('ui.wa.app_secret_hint') }}</p>
                        </div>
                        <div>
                            <x-input-label for="webhook_url" :value="__('ui.wa.webhook_url')" />
                            <x-text-input id="webhook_url" class="mt-1 block w-full bg-gray-50" :value="route('wa-webhook.verify')" readonly />
                        </div>
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $gateway?->is_active ?? false)) class="rounded border-gray-300 text-lentera-700 focus:ring-lentera-500">
                            {{ __('ui.wa.is_active') }}
                        </label>
                    </div>
                    <div class="mt-6">
                        <x-primary-button>{{ __('ui.save') }}</x-primary-button>
                    </div>
                </form>
            </div>

            <div class="rounded-lg bg-white p-6 shadow-soft">
                <h2 class="mb-2 text-base font-semibold text-gray-800">{{ __('ui.wa.test_title') }}</h2>
                <p class="mb-4 text-sm text-gray-500">{{ __('ui.wa.test_hint') }}</p>
                <form method="POST" action="{{ route('wa-gateway.test') }}" class="flex items-end gap-3">
                    @csrf
                    <div class="flex-1">
                        <x-input-label for="test_phone" :value="__('ui.wa.test_phone')" />
                        <x-text-input id="test_phone" name="test_phone" class="mt-1 block w-full" :value="old('test_phone')" required />
                    </div>
                    <x-secondary-button type="submit">{{ __('ui.wa.test_send') }}</x-secondary-button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
