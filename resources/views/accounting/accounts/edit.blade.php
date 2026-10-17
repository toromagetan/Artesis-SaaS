<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.accounting.account_edit') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
            <div class="mb-4">
                <a href="{{ route('accounts.show', $account) }}" class="text-sm text-gray-600 underline">{{ __('ui.back') }}</a>
            </div>
            <div class="rounded-lg bg-white p-6 shadow-soft">
                @if ($hasLines)
                    <div class="mb-4 rounded-lg border border-gold/50 bg-gold/10 px-4 py-3 text-sm text-gold-dark">{{ __('ui.accounting.locked_note') }}</div>
                @endif
                <form method="POST" action="{{ route('accounts.update', $account) }}">
                    @csrf
                    @method('PUT')
                    <div class="grid gap-4">
                        <div>
                            <x-input-label for="code" :value="__('ui.accounting.code')" />
                            <x-text-input id="code" name="code" class="mt-1 block w-full" :value="old('code', $account->code)" required maxlength="20" :disabled="$hasLines" />
                            @if ($hasLines)
                                <input type="hidden" name="code" value="{{ $account->code }}">
                            @endif
                            <x-input-error :messages="$errors->get('code')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="name" :value="__('ui.accounting.name')" />
                            <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name', $account->name)" required maxlength="150" />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="group" :value="__('ui.accounting.group')" />
                            <select id="group" name="group" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500" required @disabled($hasLines)>
                                @foreach (\App\Modules\Accounting\Models\Account::GROUPS as $group)
                                    <option value="{{ $group }}" @selected(old('group', $account->group) === $group)>{{ __('ui.accounting.groups.'.$group) }}</option>
                                @endforeach
                            </select>
                            @if ($hasLines)
                                <input type="hidden" name="group" value="{{ $account->group }}">
                            @endif
                            <x-input-error :messages="$errors->get('group')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="parent_id" :value="__('ui.accounting.parent')" />
                            <select id="parent_id" name="parent_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500" @disabled($hasLines)>
                                <option value="">{{ __('ui.accounting.parent_none') }}</option>
                                @foreach ($parents as $parent)
                                    <option value="{{ $parent->id }}" @selected((string) old('parent_id', $account->parent_id) === (string) $parent->id)>{{ $parent->code }} — {{ $parent->name }} ({{ __('ui.accounting.groups.'.$parent->group) }})</option>
                                @endforeach
                            </select>
                            @if ($hasLines)
                                <input type="hidden" name="parent_id" value="{{ $account->parent_id }}">
                            @endif
                            <x-input-error :messages="$errors->get('parent_id')" class="mt-2" />
                        </div>
                        <div class="flex items-center justify-end gap-2 pt-2">
                            <x-primary-button>{{ __('ui.save') }}</x-primary-button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
