<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.api_tokens.title') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-lg border border-lentera-200 bg-lentera-50 px-4 py-3 text-sm text-lentera-800">{{ session('status') }}</div>
            @endif
            @if (session('plain_token'))
                <div class="rounded-lg border border-gold/50 bg-gold/10 px-4 py-3 text-sm text-gold-dark">
                    <p class="font-medium">{{ __('ui.api_tokens.plain_once') }}</p>
                    <code class="mt-1 block break-all rounded bg-white px-3 py-2 font-mono text-sm text-gray-800">{{ session('plain_token') }}</code>
                </div>
            @endif

            <div class="rounded-lg bg-white p-6 shadow-soft">
                <h2 class="font-semibold text-gray-800">{{ __('ui.api_tokens.issue') }}</h2>
                <form method="POST" action="{{ route('api-tokens.store') }}" class="mt-4 space-y-4">
                    @csrf
                    <div>
                        <x-input-label for="name" :value="__('ui.api_tokens.name')" />
                        <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name')" required maxlength="100" />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label :value="__('ui.api_tokens.scopes')" />
                        <div class="mt-2 flex flex-wrap gap-4 text-sm">
                            @foreach (\App\Modules\Platform\Models\ApiToken::SCOPES as $scope)
                                <label class="inline-flex items-center gap-2">
                                    <input type="checkbox" name="scopes[]" value="{{ $scope }}" class="rounded border-gray-300 text-lentera-600 focus:ring-lentera-500" @checked(in_array($scope, old('scopes', []), true))>
                                    {{ __('ui.api_tokens.scope_names.'.$scope) }}
                                </label>
                            @endforeach
                        </div>
                        <x-input-error :messages="$errors->get('scopes')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="expires_at" :value="__('ui.api_tokens.expires_at')" />
                        <input id="expires_at" name="expires_at" type="datetime-local" value="{{ old('expires_at') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                        <p class="mt-1 text-xs text-gray-400">{{ __('ui.api_tokens.expires_hint') }}</p>
                        <x-input-error :messages="$errors->get('expires_at')" class="mt-2" />
                    </div>
                    <div class="flex justify-end">
                        <x-primary-button>{{ __('ui.api_tokens.issue') }}</x-primary-button>
                    </div>
                </form>
                <p class="mt-3 text-xs text-gray-400">{{ __('ui.api_tokens.hint') }}</p>
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.api_tokens.name') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.api_tokens.scopes') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.api_tokens.last_used') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.api_tokens.expires_at') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.api_tokens.status') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($tokens as $token)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 font-medium text-gray-800">{{ $token->name }}</td>
                                <td class="px-4 py-3">{{ collect($token->scopes)->map(fn ($s) => __('ui.api_tokens.scope_names.'.$s))->implode(', ') }}</td>
                                <td class="px-4 py-3 tnum">{{ $token->last_used_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                <td class="px-4 py-3 tnum">{{ $token->expires_at?->format('d/m/Y H:i') ?? __('ui.api_tokens.never_expires') }}</td>
                                <td class="px-4 py-3">
                                    @if ($token->revoked_at)
                                        <span class="text-red-600">{{ __('ui.api_tokens.revoked_label') }}</span>
                                    @elseif ($token->isExpired())
                                        <span class="text-red-600">{{ __('ui.api_tokens.expired_label') }}</span>
                                    @else
                                        <span class="text-lentera-700">{{ __('ui.active') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @unless ($token->revoked_at)
                                        <x-lentera.aksi-baris>
                                            <form method="POST" action="{{ route('api-tokens.revoke', $token) }}" onsubmit="return confirm('{{ __('ui.api_tokens.revoke_confirm') }}')">
                                                @csrf
                                                <button type="submit" class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-red-300 bg-white px-2.5 py-1.5 text-[13px] font-medium text-red-600 shadow-soft transition duration-150 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-lentera-500">{{ __('ui.api_tokens.revoke') }}</button>
                                            </form>
                                        </x-lentera.aksi-baris>
                                    @endunless
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><x-lentera.empty-state>{{ __('ui.api_tokens.empty') }}</x-lentera.empty-state></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
