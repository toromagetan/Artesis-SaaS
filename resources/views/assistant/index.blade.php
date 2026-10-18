<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.ai.title') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl space-y-6 sm:px-6 lg:px-8">
            <div class="rounded-lg bg-white p-6 shadow-soft">
                <form method="POST" action="{{ route('assistant.ask') }}" class="flex gap-3">
                    @csrf
                    <x-text-input name="question" class="block w-full" :value="$question" required maxlength="500" placeholder="{{ __('ui.ai.placeholder') }}" />
                    <x-primary-button>{{ __('ui.ai.ask') }}</x-primary-button>
                </form>
                <p class="mt-3 text-xs text-gray-400">{{ __('ui.ai.examples') }}</p>
            </div>

            @if ($result)
                <div class="rounded-lg bg-white p-6 shadow-soft">
                    <p class="text-sm text-gray-500">{{ $question }}</p>
                    @if ($result['status'] === 'ok')
                        <p class="mt-2 text-2xl font-semibold text-lentera-900">{{ $result['text'] }}</p>
                        <div class="mt-4 flex flex-wrap gap-2 text-xs">
                            <span class="rounded-full bg-lentera-50 px-2.5 py-1 font-medium text-lentera-800 ring-1 ring-inset ring-lentera-600/20">{{ __('ui.ai.basis_period') }}: {{ $result['basis']['from'] ?? '' }} – {{ $result['basis']['to'] ?? '' }}</span>
                            <span class="rounded-full bg-lentera-50 px-2.5 py-1 font-medium text-lentera-800 ring-1 ring-inset ring-lentera-600/20">{{ __('ui.ai.basis_branches') }}: {{ implode(', ', $result['basis']['branches'] ?? []) }}</span>
                            @if (! empty($result['basis']['source']))
                                <span class="rounded-full bg-lentera-50 px-2.5 py-1 font-medium text-lentera-800 ring-1 ring-inset ring-lentera-600/20">{{ __('ui.ai.basis_source') }}: {{ $result['basis']['source'] }}</span>
                            @endif
                        </div>
                    @elseif ($result['status'] === 'ai_not_configured')
                        <p class="mt-2 rounded-lg border border-gold/50 bg-gold/10 px-4 py-3 text-sm text-gold-dark">{{ __('ui.ai.not_configured') }}</p>
                    @elseif ($result['status'] === 'ai_provider_error')
                        <p class="mt-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ __('ui.ai.provider_error') }}</p>
                    @else
                        <p class="mt-2 text-sm text-gray-600">{{ __('ui.ai.unknown') }}</p>
                    @endif
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
