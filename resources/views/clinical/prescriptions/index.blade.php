<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.prescriptions.title') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-lg border border-lentera-200 bg-lentera-50 px-4 py-3 text-sm text-lentera-800">{{ session('status') }}</div>
            @endif

            <div class="mb-4 flex items-center justify-between">
                <form method="GET" class="flex gap-2">
                    <x-text-input name="q" :value="$q" :placeholder="__('ui.prescriptions.search_placeholder')" class="w-72" />
                    <select name="cabang" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                        <option value="">{{ __('ui.prescriptions.exam_branch') }}: {{ __('ui.search') }}</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected((string) $branchId === (string) $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                    <x-secondary-button type="submit">{{ __('ui.search') }}</x-secondary-button>
                </form>
                @can('prescriptions.create')
                    <x-lentera.aksi-bar>
                        <x-lentera.menu-item :href="route('prescriptions.pick-customer')" primary>{{ __('ui.prescriptions.add_new') }}</x-lentera.menu-item>
                    </x-lentera.aksi-bar>
                @endcan
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.prescriptions.examined_at') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.prescriptions.customer') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.prescriptions.exam_branch') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.prescriptions.examiner') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.prescriptions.eye_right') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.prescriptions.eye_left') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.prescriptions.status') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($prescriptions as $prescription)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 whitespace-nowrap">{{ $prescription->examined_at->format('d/m/Y') }}</td>
                                <td class="px-4 py-3 font-medium text-gray-800">
                                    <a href="{{ route('prescriptions.show', $prescription) }}" class="text-lentera-800 underline-offset-2 hover:underline">{{ $prescription->customer->name }}</a>
                                </td>
                                <td class="px-4 py-3">{{ $prescription->branch->name }}</td>
                                <td class="px-4 py-3">{{ $prescription->examiner?->name ?? __('ui.prescriptions.examiner_none') }}</td>
                                <td class="px-4 py-3 tabular-nums">{{ number_format((float) $prescription->od_sph, 2) }} / {{ number_format((float) $prescription->od_cyl, 2) }}</td>
                                <td class="px-4 py-3 tabular-nums">{{ number_format((float) $prescription->os_sph, 2) }} / {{ number_format((float) $prescription->os_cyl, 2) }}</td>
                                <td class="px-4 py-3">
                                    @if ($prescription->used_at)
                                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500">{{ __('ui.prescriptions.used_badge') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <x-lentera.aksi-baris>
                                        <x-lentera.menu-item :href="route('prescriptions.show', $prescription)" sm>{{ __('ui.prescriptions.detail') }}</x-lentera.menu-item>
                                        @can('prescriptions.create')
                                            <x-lentera.menu-item :href="route('prescriptions.edit', $prescription)" sm>{{ __('ui.edit') }}</x-lentera.menu-item>
                                        @endcan
                                        @can('prescriptions.print')
                                            <x-lentera.menu-item :href="route('prescriptions.print', $prescription)" sm>{{ __('ui.prescriptions.print') }}</x-lentera.menu-item>
                                        @endcan
                                    </x-lentera.aksi-baris>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8"><x-lentera.empty-state>{{ __('ui.prescriptions.empty') }}</x-lentera.empty-state></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $prescriptions->links() }}</div>
        </div>
    </div>
</x-app-layout>
