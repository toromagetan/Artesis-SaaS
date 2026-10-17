<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('ui.users.create') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-lg p-6">
                <form method="POST" action="{{ route('users.store') }}">
                    @csrf
                    <div class="grid gap-4">
                        <div>
                            <x-input-label for="name" :value="__('ui.users.name')" />
                            <x-text-input id="name" name="name" class="block mt-1 w-full" :value="old('name')" required />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="username" :value="__('ui.users.username')" />
                            <x-text-input id="username" name="username" class="block mt-1 w-full" :value="old('username')" required />
                            <x-input-error :messages="$errors->get('username')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="email" :value="__('ui.users.email_optional')" />
                            <x-text-input id="email" name="email" type="email" class="block mt-1 w-full" :value="old('email')" />
                            <x-input-error :messages="$errors->get('email')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="phone" :value="__('ui.users.phone_optional')" />
                            <x-text-input id="phone" name="phone" class="block mt-1 w-full" :value="old('phone')" />
                            <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="password" :value="__('ui.users.password')" />
                            <x-text-input id="password" name="password" type="password" class="block mt-1 w-full" required />
                            <x-input-error :messages="$errors->get('password')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label :value="__('ui.users.roles')" />
                            <div class="mt-2 flex flex-wrap gap-3">
                                @foreach ($roles as $role)
                                    <label class="inline-flex items-center gap-2 text-sm">
                                        <input type="checkbox" name="roles[]" value="{{ $role->name }}" class="rounded border-gray-300" @checked(in_array($role->name, old('roles', [])))>
                                        {{ $role->label }}
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <div>
                            <x-input-label :value="__('ui.users.managed_branches')" />
                            <div class="mt-2 flex flex-wrap gap-3">
                                @foreach ($branches as $branch)
                                    <label class="inline-flex items-center gap-2 text-sm">
                                        <input type="checkbox" name="branches[]" value="{{ $branch->id }}" class="rounded border-gray-300" @checked(in_array($branch->id, old('branches', [])))>
                                        {{ $branch->name }}
                                    </label>
                                @endforeach
                            </div>
                            <p class="text-xs text-gray-400 mt-1">{{ __('ui.users.branches_owner_hint') }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 mt-6">
                        <a href="{{ route('users.index') }}" class="text-sm text-gray-600 underline">{{ __('ui.back') }}</a>
                        <x-primary-button>{{ __('ui.save') }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
