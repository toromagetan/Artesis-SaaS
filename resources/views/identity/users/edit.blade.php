<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('ui.users.edit') }} — {{ $user->name }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-lg p-6">
                <form method="POST" action="{{ route('users.update', $user) }}">
                    @csrf
                    @method('PUT')
                    <div class="grid gap-4">
                        <div>
                            <x-input-label :value="__('ui.users.username')" />
                            <x-text-input class="block mt-1 w-full bg-gray-50" :value="$user->username" disabled />
                            <p class="text-xs text-gray-400 mt-1">{{ __('ui.users.username_immutable') }}</p>
                        </div>
                        <div>
                            <x-input-label for="name" :value="__('ui.users.name')" />
                            <x-text-input id="name" name="name" class="block mt-1 w-full" :value="old('name', $user->name)" required />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="email" :value="__('ui.users.email_optional')" />
                            <x-text-input id="email" name="email" type="email" class="block mt-1 w-full" :value="old('email', $user->email)" />
                            <x-input-error :messages="$errors->get('email')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="phone" :value="__('ui.users.phone_optional')" />
                            <x-text-input id="phone" name="phone" class="block mt-1 w-full" :value="old('phone', $user->phone)" />
                            <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="password" :value="__('ui.users.password_new_optional')" />
                            <x-text-input id="password" name="password" type="password" class="block mt-1 w-full" />
                            <x-input-error :messages="$errors->get('password')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label :value="__('ui.users.roles')" />
                            <div class="mt-2 flex flex-wrap gap-3">
                                @foreach ($roles as $role)
                                    <label class="inline-flex items-center gap-2 text-sm">
                                        <input type="checkbox" name="roles[]" value="{{ $role->name }}" class="rounded border-gray-300" @checked(in_array($role->name, old('roles', $user->roles->pluck('name')->all())))>
                                        {{ $role->label }}
                                    </label>
                                @endforeach
                            </div>
                            <x-input-error :messages="$errors->get('roles')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label :value="__('ui.users.managed_branches')" />
                            <div class="mt-2 flex flex-wrap gap-3">
                                @foreach ($branches as $branch)
                                    <label class="inline-flex items-center gap-2 text-sm">
                                        <input type="checkbox" name="branches[]" value="{{ $branch->id }}" class="rounded border-gray-300" @checked(in_array($branch->id, old('branches', $user->branches->pluck('id')->all())))>
                                        {{ $branch->name }}
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 mt-6">
                        <a href="{{ route('users.index') }}" class="text-sm text-gray-600 underline">{{ __('ui.back') }}</a>
                        <x-primary-button>{{ __('ui.save_changes') }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
