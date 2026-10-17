<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.accounting.account_create') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
            <div class="mb-4">
                <a href="{{ route('accounts.index') }}" class="text-sm text-gray-600 underline">{{ __('ui.back') }}</a>
            </div>
            <div class="rounded-lg bg-white p-6 shadow-soft">
                <form method="POST" action="{{ route('accounts.store') }}">
                    @csrf
                    <div class="grid gap-4">
                        <div>
                            <x-input-label for="code" :value="__('ui.accounting.code')" />
                            <x-text-input id="code" name="code" class="mt-1 block w-full" :value="old('code')" required maxlength="20" />
                            <x-input-error :messages="$errors->get('code')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="name" :value="__('ui.accounting.name')" />
                            <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name')" required maxlength="150" />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="group" :value="__('ui.accounting.group')" />
                            <select id="group" name="group" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500" required>
                                @foreach (\App\Modules\Accounting\Models\Account::GROUPS as $group)
                                    <option value="{{ $group }}" @selected(old('group') === $group)>{{ __('ui.accounting.groups.'.$group) }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('group')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="parent_id" :value="__('ui.accounting.parent')" />
                            <select id="parent_id" name="parent_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                                <option value="">{{ __('ui.accounting.parent_none') }}</option>
                                @foreach ($parents as $parent)
                                    <option value="{{ $parent->id }}" @selected((string) old('parent_id') === (string) $parent->id)>{{ $parent->code }} — {{ $parent->name }} ({{ __('ui.accounting.groups.'.$parent->group) }})</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('parent_id')" class="mt-2" />
                            <p class="mt-1 text-xs text-gray-500">{{ __('ui.accounting.parent_hint') }}</p>
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
