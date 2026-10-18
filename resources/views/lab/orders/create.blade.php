<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.lab_orders.create') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
            @if ($errors->any())
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            <div class="mb-4">
                <a href="{{ route('lab-orders.index') }}" class="text-sm text-gray-600 underline">{{ __('ui.back') }}</a>
            </div>

            <div class="rounded-lg bg-white p-6 shadow-soft">
                <form method="POST" action="{{ route('lab-orders.store') }}" class="space-y-5">
                    @csrf
                    <div>
                        <label for="sale_id" class="mb-1 block text-sm font-medium text-gray-700">{{ __('ui.lab_orders.sale') }} <span class="text-red-600">*</span></label>
                        <select id="sale_id" name="sale_id" class="w-full rounded-lg border-gray-300 text-sm focus:border-lentera-500 focus:ring-lentera-500" required>
                            <option value="">—</option>
                            @foreach ($sales as $sale)
                                <option value="{{ $sale->id }}" @selected((int) old('sale_id') === $sale->id)>{{ $sale->number }} · {{ $sale->customer->name }} · {{ $sale->branch->name }}</option>
                            @endforeach
                        </select>
                        @error('sale_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="lab_name" class="mb-1 block text-sm font-medium text-gray-700">{{ __('ui.lab_orders.lab_name') }} <span class="text-red-600">*</span></label>
                        <input id="lab_name" name="lab_name" type="text" maxlength="100" value="{{ old('lab_name') }}" class="w-full rounded-lg border-gray-300 text-sm focus:border-lentera-500 focus:ring-lentera-500" required>
                        @error('lab_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="lab_cost" class="mb-1 block text-sm font-medium text-gray-700">{{ __('ui.lab_orders.cost') }}</label>
                        <input id="lab_cost" name="lab_cost" type="number" min="0" step="0.01" value="{{ old('lab_cost', '0') }}" class="w-full rounded-lg border-gray-300 text-sm tnum focus:border-lentera-500 focus:ring-lentera-500">
                        @error('lab_cost') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex justify-end gap-2 border-t border-gray-100 pt-4">
                        <a href="{{ route('lab-orders.index') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-600">{{ __('ui.cancel') }}</a>
                        <button type="submit" class="rounded-lg bg-lentera-700 px-4 py-2 text-sm font-semibold text-white transition duration-150 hover:bg-lentera-800">{{ __('ui.save') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
