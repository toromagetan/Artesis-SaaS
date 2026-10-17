<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.import.title') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
            @if ($errors->any())
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif
            <div class="rounded-lg bg-white p-6 shadow-soft">
                <p class="mb-4 text-sm text-gray-600">{{ __('ui.import.intro') }}</p>
                <p class="mb-6">
                    <a href="{{ route('stock.import.template') }}" class="text-sm font-medium text-lentera-800 underline">{{ __('ui.import.template') }}</a>
                </p>
                <form method="POST" action="{{ route('stock.import.preview') }}" enctype="multipart/form-data">
                    @csrf
                    <div>
                        <x-input-label for="file" :value="__('ui.import.file')" />
                        <input id="file" name="file" type="file" accept=".csv,text/csv,text/plain" required
                               class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-lentera-500 focus:outline-none focus:ring-lentera-500">
                    </div>
                    <div class="mt-6 flex items-center gap-3">
                        <a href="{{ route('stock.index') }}" class="text-sm text-gray-600 underline">{{ __('ui.back') }}</a>
                        <x-primary-button>{{ __('ui.import.upload') }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
