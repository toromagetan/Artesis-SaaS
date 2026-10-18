<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.branches.edit') }} — {{ $branch->name }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-lg p-6">
                <form method="POST" action="{{ route('branches.update', $branch) }}">
                    @csrf
                    @method('PUT')
                    <div class="grid gap-4">
                        <div>
                            <x-input-label for="name" :value="__('ui.branches.name')" />
                            <x-text-input id="name" name="name" class="block mt-1 w-full" :value="old('name', $branch->name)" required />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="phone" :value="__('ui.branches.phone_optional')" />
                            <x-text-input id="phone" name="phone" class="block mt-1 w-full" :value="old('phone', $branch->phone)" />
                            <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="address" :value="__('ui.branches.address_optional')" />
                            <textarea id="address" name="address" rows="3" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">{{ old('address', $branch->address) }}</textarea>
                            <x-input-error :messages="$errors->get('address')" class="mt-2" />
                        </div>
                    </div>
                    <label class="mt-4 inline-flex items-center gap-2 text-sm">
                        <input type="checkbox" name="is_main_warehouse" value="1" class="rounded border-gray-300 text-lentera-600 focus:ring-lentera-500" @checked(old('is_main_warehouse', $branch->is_main_warehouse))>
                        {{ __('ui.branches.is_main_warehouse') }}
                    </label>
                    <div class="flex items-center gap-3 mt-6">
                        <a href="{{ route('branches.index') }}" class="text-sm text-gray-600 underline">{{ __('ui.back') }}</a>
                        <x-primary-button>{{ __('ui.save_changes') }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
