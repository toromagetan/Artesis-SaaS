<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.prescriptions.detail') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-lg border border-lentera-200 bg-lentera-50 px-4 py-3 text-sm text-lentera-800">{{ session('status') }}</div>
            @endif

            <div class="mb-4 flex items-center justify-between">
                <a href="{{ route('customers.show', $prescription->customer) }}" class="text-sm text-gray-600 underline">{{ __('ui.prescriptions.back_to_customer') }}</a>
                <div class="flex items-center gap-2">
                    <x-lentera.aksi-bar>
                        @can('prescriptions.print')
                            <x-lentera.menu-item :href="route('prescriptions.print', $prescription)" target="_blank" print>{{ __('ui.prescriptions.print') }}</x-lentera.menu-item>
                        @endcan
                        <x-lentera.menu-item :href="route('prescriptions.precal-print', $prescription)" target="_blank" print>{{ __('ui.precal.print') }}</x-lentera.menu-item>
                    </x-lentera.aksi-bar>
                    @if (auth()->user()->hasPermission('prescriptions.create'))
                        <x-lentera.aksi-bar>
                            <x-lentera.menu-item :href="route('prescriptions.create', $prescription->customer)">{{ __('ui.prescriptions.create') }}</x-lentera.menu-item>
                            <x-lentera.menu-item :href="route('prescriptions.precal', $prescription)">{{ __('ui.prescriptions.view_precal') }}</x-lentera.menu-item>
                            @unless ($prescription->isLocked())
                                <x-lentera.menu-item :href="route('prescriptions.edit', $prescription)">{{ __('ui.edit') }}</x-lentera.menu-item>
                            @endunless

                            <form method="POST" action="{{ route('prescriptions.duplicate', $prescription) }}">
                                @csrf
                                <button type="submit" class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-sm font-medium text-gray-700 shadow-soft transition duration-150 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-lentera-500">{{ __('ui.prescriptions.duplicate') }}</button>
                            </form>
                        </x-lentera.aksi-bar>
                    @endif
                </div>
            </div>

            @if ($prescription->isLocked())
                <div class="mb-4 rounded-lg border border-gold/50 bg-gold/10 px-4 py-3 text-sm text-gold-dark">{{ __('ui.prescriptions.locked') }}</div>
            @endif

            <div class="rounded-lg bg-white p-6 shadow-soft">
                <div class="mb-4 flex items-center gap-3">
                    <h2 class="text-lg font-semibold text-gray-800">{{ $prescription->customer->name }}</h2>
                    @if ($prescription->isLocked())
                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500">{{ __('ui.prescriptions.used_badge') }}</span>
                    @endif
                </div>
                <dl class="mb-6 grid grid-cols-[160px_1fr] gap-y-3 text-sm">
                    <dt class="text-gray-500">{{ __('ui.prescriptions.examined_at') }}</dt>
                    <dd class="font-medium text-gray-800">{{ $prescription->examined_at->format('d/m/Y') }}</dd>
                    <dt class="text-gray-500">{{ __('ui.prescriptions.examiner') }}</dt>
                    <dd class="font-medium text-gray-800">{{ $prescription->examiner?->name ?: '—' }}</dd>
                    <dt class="text-gray-500">{{ __('ui.customers.home_branch') }}</dt>
                    <dd class="font-medium text-gray-800">{{ $prescription->branch->name }}</dd>
                </dl>

                <div class="grid gap-4 md:grid-cols-2">
                    @foreach (['od' => 'eye_right', 'os' => 'eye_left'] as $side => $labelKey)
                        <div class="rounded-lg border border-gray-200 p-4">
                            <h3 class="mb-3 text-sm font-semibold text-lentera-900">{{ __('ui.prescriptions.'.$labelKey) }}</h3>
                            <dl class="grid grid-cols-2 gap-y-2 text-sm tnum">
                                <dt class="text-gray-500">{{ __('ui.prescriptions.sph') }}</dt>
                                <dd class="font-medium text-gray-800">{{ $prescription->{$side.'_sph'} }}</dd>
                                <dt class="text-gray-500">{{ __('ui.prescriptions.cyl') }}</dt>
                                <dd class="font-medium text-gray-800">{{ $prescription->{$side.'_cyl'} }}</dd>
                                <dt class="text-gray-500">{{ __('ui.prescriptions.axis') }}</dt>
                                <dd class="font-medium text-gray-800">{{ $prescription->{$side.'_axis'} ?? '—' }}</dd>
                                <dt class="text-gray-500">{{ __('ui.prescriptions.add') }}</dt>
                                <dd class="font-medium text-gray-800">{{ $prescription->{$side.'_add'} ?? '—' }}</dd>
                                <dt class="text-gray-500">{{ __('ui.prescriptions.'.($side === 'od' ? 'pdr' : 'pdl')) }}</dt>
                                <dd class="font-medium text-gray-800">{{ $prescription->{$side.($side === 'od' ? '_pdr' : '_pdl')} ?? '—' }}</dd>
                            </dl>
                        </div>
                    @endforeach
                </div>

                <dl class="mt-6 grid grid-cols-[160px_1fr] gap-y-3 text-sm">
                    <dt class="text-gray-500">{{ __('ui.prescriptions.pd_total') }}</dt>
                    <dd class="font-medium text-gray-800 tnum">{{ $prescription->pd_total ?? '—' }}</dd>
                    <dt class="text-gray-500">{{ __('ui.prescriptions.prism') }}</dt>
                    <dd class="font-medium text-gray-800">{{ $prescription->prism ?: '—' }}</dd>
                    @can('medical_notes.view')
                        <dt class="text-gray-500">{{ __('ui.prescriptions.notes') }}</dt>
                        <dd class="font-medium text-gray-800">{{ $prescription->notes ?: '—' }}</dd>
                    @endcan
                </dl>
            </div>
        </div>
    </div>
</x-app-layout>
