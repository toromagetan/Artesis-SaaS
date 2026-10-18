<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.opname.create') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
            <div class="rounded-lg bg-white p-6 shadow-soft">
                <form method="POST" action="{{ route('opnames.store') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label for="branch_id" class="mb-1 block text-sm font-medium text-gray-700">{{ __('ui.opname.branch') }}</label>
                        <select id="branch_id" name="branch_id" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-lentera-500 focus:ring-lentera-500" required>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}" @selected(old('branch_id') == $branch->id)>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                        @error('branch_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="scheduled_date" class="mb-1 block text-sm font-medium text-gray-700">{{ __('ui.opname.date') }}</label>
                        <input id="scheduled_date" type="date" name="scheduled_date" value="{{ old('scheduled_date', now()->toDateString()) }}" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-lentera-500 focus:ring-lentera-500" required>
                        @error('scheduled_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="notes" class="mb-1 block text-sm font-medium text-gray-700">{{ __('ui.opname.notes') }}</label>
                        <input id="notes" type="text" name="notes" value="{{ old('notes') }}" maxlength="500" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                        @error('notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex items-center gap-3 pt-2">
                        <x-primary-button>{{ __('ui.save') }}</x-primary-button>
                        <a href="{{ route('opnames.index') }}" class="text-sm text-gray-500 hover:text-gray-700">{{ __('ui.back') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
