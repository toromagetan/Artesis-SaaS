<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.product_import.title') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-lg border border-lentera-200 bg-lentera-50 px-4 py-3 text-sm text-lentera-800">{{ session('status') }}</div>
            @endif

            <div class="rounded-lg bg-white p-6 shadow-soft">
                <p class="text-sm text-gray-600">{{ __('ui.product_import.intro') }}</p>
                <p class="mt-2 text-sm text-gray-600">{{ __('ui.product_import.note') }}</p>

                <div class="mt-4">
                    <a href="{{ route('products.import.template') }}" class="text-sm font-medium text-lentera-700 underline underline-offset-2">{{ __('ui.product_import.template') }}</a>
                </div>

                <form method="POST" action="{{ route('products.import.store') }}" enctype="multipart/form-data" class="mt-6">
                    @csrf
                    <div>
                        <x-input-label for="file" :value="__('ui.product_import.file')" />
                        <input id="file" name="file" type="file" accept=".xlsx,.csv" class="mt-2 block w-full text-sm" required>
                        <x-input-error :messages="$errors->get('file')" class="mt-2" />
                    </div>
                    <div class="mt-6 flex items-center gap-3">
                        <a href="{{ route('products.index', 'frame') }}" class="text-sm text-gray-600 underline">{{ __('ui.back') }}</a>
                        <x-primary-button>{{ __('ui.product_import.submit') }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
