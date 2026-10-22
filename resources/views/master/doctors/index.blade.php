<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.doctors.title') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-lg border border-lentera-200 bg-lentera-50 px-4 py-3 text-sm text-lentera-800">{{ session('status') }}</div>
            @endif

            <div class="mb-4 flex justify-end">
                <x-lentera.aksi-bar>
                    <x-lentera.menu-item :href="route('doctors.create')" primary>{{ __('ui.doctors.create') }}</x-lentera.menu-item>
                </x-lentera.aksi-bar>
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.doctors.name') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.doctors.clinic') }}</th>
                            <th class="px-4 py-3 font-medium text-right">{{ __('ui.doctors.commission_rate') }}</th>
                            <th class="px-4 py-3 font-medium text-right">{{ __('ui.doctors.referrals') }}</th>
                            <th class="px-4 py-3 font-medium text-right">{{ __('ui.doctors.total_commission') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.doctors.status') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($doctors as $doctor)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 font-medium text-gray-800">
                                    <a href="{{ route('doctors.show', $doctor) }}" class="text-lentera-800 underline decoration-lentera-300 underline-offset-2 hover:text-lentera-950">{{ $doctor->name }}</a>
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ $doctor->clinic ?: '—' }}</td>
                                <td class="px-4 py-3 text-right tnum">{{ rtrim(rtrim(number_format((float) $doctor->commission_rate, 2, ',', '.'), '0'), ',') }}%</td>
                                <td class="px-4 py-3 text-right tnum">{{ $doctor->referrals_count }}</td>
                                <td class="px-4 py-3 text-right tnum">{{ \App\Support\Money::fromDecimal((string) ($doctor->commission_total ?? '0'))->format() }}</td>
                                <td class="px-4 py-3"><x-lentera.badge :active="$doctor->is_active" /></td>
                                <td class="px-4 py-3 text-right">
                                    <x-lentera.aksi-baris>
                                        <x-lentera.menu-item :href="route('doctors.show', $doctor)" sm>{{ __('ui.doctors.detail') }}</x-lentera.menu-item>
                                        <x-lentera.menu-item :href="route('doctors.edit', $doctor)" sm>{{ __('ui.edit') }}</x-lentera.menu-item>

                                        <form method="POST" action="{{ route('doctors.toggle-active', $doctor) }}">
                                            @csrf
                                            <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-[13px] font-medium shadow-soft transition duration-150 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-lentera-500 {{ $doctor->is_active ? 'text-red-600' : 'text-lentera-700' }}">{{ $doctor->is_active ? __('ui.deactivate') : __('ui.activate') }}</button>
                                        </form>
                                    </x-lentera.aksi-baris>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><x-lentera.empty-state>{{ __('ui.doctors.empty') }}</x-lentera.empty-state></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $doctors->links() }}</div>
        </div>
    </div>
</x-app-layout>
