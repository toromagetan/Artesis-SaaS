<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.qc.edit') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
            <div class="mb-4">
                <a href="{{ route('qc-templates.index') }}" class="text-sm text-gray-600 underline">{{ __('ui.back') }}</a>
            </div>

            <div class="rounded-lg bg-white p-6 shadow-soft">
                <form method="POST" action="{{ route('qc-templates.update', $template) }}" class="space-y-5">
                    @csrf
                    @method('PUT')
                    <div>
                        <label for="name" class="mb-1 block text-sm font-medium text-gray-700">{{ __('ui.qc.name') }} <span class="text-red-600">*</span></label>
                        <input id="name" name="name" type="text" maxlength="100" value="{{ old('name', $template->name) }}" class="w-full rounded-lg border-gray-300 text-sm focus:border-lentera-500 focus:ring-lentera-500" required>
                        @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="sort_order" class="mb-1 block text-sm font-medium text-gray-700">{{ __('ui.qc.sort_order') }}</label>
                        <input id="sort_order" name="sort_order" type="number" min="0" max="999" value="{{ old('sort_order', $template->sort_order) }}" class="w-full rounded-lg border-gray-300 text-sm focus:border-lentera-500 focus:ring-lentera-500" required>
                        @error('sort_order') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="flex items-start gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="is_required" value="1" @checked(old('is_required', $template->is_required)) class="mt-0.5 rounded border-gray-300 text-lentera-600 focus:ring-lentera-500">
                            <span>{{ __('ui.qc.required') }}<span class="block text-xs text-gray-400">{{ __('ui.qc.required_hint') }}</span></span>
                        </label>
                    </div>
                    <div class="flex justify-end gap-2 border-t border-gray-100 pt-4">
                        <a href="{{ route('qc-templates.index') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-600">{{ __('ui.cancel') }}</a>
                        <button type="submit" class="rounded-lg bg-lentera-700 px-4 py-2 text-sm font-semibold text-white transition duration-150 hover:bg-lentera-800">{{ __('ui.save') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
