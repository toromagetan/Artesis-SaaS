<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.prescriptions.create') }} — {{ $customer->name }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
            <div class="rounded-lg bg-white p-6 shadow-soft">
                <form method="POST" action="{{ route('prescriptions.store', $customer) }}">
                    @csrf
                    @include('clinical.prescriptions.partials.form-fields')
                    <div class="mt-6 flex items-center gap-3">
                        <a href="{{ route('customers.show', $customer) }}" class="text-sm text-gray-600 underline">{{ __('ui.back') }}</a>
                        <x-primary-button>{{ __('ui.save') }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
