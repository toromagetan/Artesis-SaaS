<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('ui.roles.edit') }} — {{ $role->label }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-lg p-6">
                <form method="POST" action="{{ route('roles.update', $role) }}">
                    @csrf
                    @method('PUT')
                    <x-input-error :messages="$errors->get('permissions')" class="mb-4" />
                    <div class="grid md:grid-cols-2 gap-6">
                        @foreach ($groups as $group)
                            <fieldset class="border border-gray-200 rounded-lg p-4">
                                <legend class="text-sm font-semibold text-gray-700 px-1">{{ $group['label'] }}</legend>
                                <div class="grid gap-2 mt-1">
                                    @foreach ($group['permissions'] as $string => $label)
                                        <label class="inline-flex items-start gap-2 text-sm">
                                            <input type="checkbox" name="permissions[]" value="{{ $string }}" class="rounded border-gray-300 mt-0.5" @checked(in_array($string, old('permissions', $granted)))>
                                            <span>{{ $label }} <span class="block text-xs text-gray-400">{{ $string }}</span></span>
                                        </label>
                                    @endforeach
                                </div>
                            </fieldset>
                        @endforeach
                    </div>
                    <div class="flex items-center gap-3 mt-6">
                        <a href="{{ route('roles.index') }}" class="text-sm text-gray-600 underline">{{ __('ui.back') }}</a>
                        <x-primary-button>{{ __('ui.roles.save') }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
