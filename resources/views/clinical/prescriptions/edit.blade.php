<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.prescriptions.edit') }} — {{ $prescription->customer->name }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-lg border border-lentera-200 bg-lentera-50 px-4 py-3 text-sm text-lentera-800">{{ session('status') }}</div>
            @endif
            @if ($prescription->isLocked())
                <div class="mb-4 rounded-lg border border-gold/50 bg-gold/10 px-4 py-3 text-sm text-gold-dark">{{ __('ui.prescriptions.locked') }}</div>
            @endif
            <div class="rounded-lg bg-white p-6 shadow-soft">
                <form method="POST" action="{{ route('prescriptions.update', $prescription) }}">
                    @csrf
                    @method('PUT')
                    @include('clinical.prescriptions.partials.form-fields', ['prescription' => $prescription])
                    <div class="mt-6 flex items-center gap-3">
                        <a href="{{ route('prescriptions.show', $prescription) }}" class="text-sm text-gray-600 underline">{{ __('ui.back') }}</a>
                        <x-primary-button>{{ __('ui.save_changes') }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
