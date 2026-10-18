<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.ai.settings_title') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl space-y-6 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-lg border border-lentera-200 bg-lentera-50 px-4 py-3 text-sm text-lentera-800">{{ session('status') }}</div>
            @endif

            <div class="rounded-lg bg-white p-6 shadow-soft">
                <form method="POST" action="{{ route('ai-setting.update') }}" class="space-y-4">
                    @csrf
                    @method('PUT')
                    <div>
                        <x-input-label for="base_url" :value="__('ui.ai.base_url')" />
                        <x-text-input id="base_url" name="base_url" class="mt-1 block w-full" :value="old('base_url', $setting->base_url)" required maxlength="255" />
                        <p class="mt-1 text-xs text-gray-400">{{ __('ui.ai.base_url_hint') }}</p>
                        <x-input-error :messages="$errors->get('base_url')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="model" :value="__('ui.ai.model')" />
                        <x-text-input id="model" name="model" class="mt-1 block w-full" :value="old('model', $setting->model)" required maxlength="100" />
                        <x-input-error :messages="$errors->get('model')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="api_key" :value="__('ui.ai.api_key')" />
                        <x-text-input id="api_key" name="api_key" type="password" class="mt-1 block w-full" autocomplete="off" />
                        <p class="mt-1 text-xs text-gray-400">
                            @if ($setting->maskedKey() !== '')
                                {{ __('ui.ai.current_key') }} <code class="font-mono">{{ $setting->maskedKey() }}</code> — {{ __('ui.ai.keep_key_hint') }}
                            @else
                                {{ __('ui.ai.no_key') }}
                            @endif
                        </p>
                        <x-input-error :messages="$errors->get('api_key')" class="mt-2" />
                    </div>
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="checkbox" name="is_active" value="1" class="rounded border-gray-300 text-lentera-600 focus:ring-lentera-500" @checked(old('is_active', $setting->is_active))>
                        {{ __('ui.ai.is_active') }}
                    </label>
                    <div class="flex justify-end">
                        <x-primary-button>{{ __('ui.ai.save') }}</x-primary-button>
                    </div>
                </form>
                <p class="mt-3 text-xs text-gray-400">{{ __('ui.ai.privacy_hint') }}</p>
            </div>
        </div>
    </div>
</x-app-layout>
