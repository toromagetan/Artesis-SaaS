<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.members.levels_title') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-lg border border-lentera-200 bg-lentera-50 px-4 py-3 text-sm text-lentera-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            <div class="mb-6 overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.members.level_name') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.members.min_accumulation') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.members.multiplier') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.members.benefits') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.status') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($levels as $level)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 font-medium text-gray-800">{{ $level->name }}</td>
                                <td class="px-4 py-3 tnum">{{ \App\Support\Money::fromDecimal((string) $level->min_accumulation)->format() }}</td>
                                <td class="px-4 py-3 tnum">{{ $level->point_multiplier }}x</td>
                                <td class="px-4 py-3 text-gray-600">{{ $level->benefits }}</td>
                                <td class="px-4 py-3"><x-lentera.badge :active="$level->is_active">{{ $level->is_active ? __('ui.active') : __('ui.inactive') }}</x-lentera.badge></td>
                                <td class="px-4 py-3 text-right">
                                    <x-lentera.aksi-baris>
                                        <form method="POST" action="{{ route('members.levels.toggle', $level) }}" onsubmit="return confirm('{{ __('ui.members.toggle_confirm') }}')">
                                            @csrf
                                            <button type="submit" class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-[13px] font-medium text-gray-700 shadow-soft transition duration-150 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-lentera-500">{{ $level->is_active ? __('ui.deactivate') : __('ui.activate') }}</button>
                                        </form>
                                    </x-lentera.aksi-baris>
                                </td>
                            </tr>
                            <tr class="border-b border-gray-50 bg-gray-50/50">
                                <td colspan="6" class="px-4 py-3">
                                    <form method="POST" action="{{ route('members.levels.update', $level) }}" class="flex flex-wrap items-end gap-3">
                                        @csrf @method('PUT')
                                        <div><x-input-label :value="__('ui.members.level_name')" /><x-text-input name="name" :value="$level->name" class="mt-1 w-40" required /></div>
                                        <div><x-input-label :value="__('ui.members.min_accumulation')" /><x-text-input name="min_accumulation" type="number" step="0.01" :value="$level->min_accumulation" class="mt-1 w-40 tnum" required /></div>
                                        <div><x-input-label :value="__('ui.members.multiplier')" /><x-text-input name="point_multiplier" type="number" step="0.01" min="0" :value="$level->point_multiplier" class="mt-1 w-24 tnum" required /></div>
                                        <div class="grow"><x-input-label :value="__('ui.members.benefits')" /><x-text-input name="benefits" :value="$level->benefits" class="mt-1 w-full" /></div>
                                        <x-secondary-button type="submit">{{ __('ui.save') }}</x-secondary-button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <div class="rounded-lg bg-white p-6 shadow-soft">
                    <h2 class="mb-4 text-base font-semibold text-gray-800">{{ __('ui.members.add_level') }}</h2>
                    <form method="POST" action="{{ route('members.levels.store') }}" class="grid gap-4">
                        @csrf
                        <div><x-input-label :value="__('ui.members.level_name')" /><x-text-input name="name" class="mt-1 block w-full" required /></div>
                        <div><x-input-label :value="__('ui.members.min_accumulation')" /><x-text-input name="min_accumulation" type="number" step="0.01" class="mt-1 block w-full tnum" required /></div>
                        <div><x-input-label :value="__('ui.members.multiplier')" /><x-text-input name="point_multiplier" type="number" step="0.01" min="0" value="1" class="mt-1 block w-full tnum" required /></div>
                        <div><x-input-label :value="__('ui.members.benefits')" /><x-text-input name="benefits" class="mt-1 block w-full" /></div>
                        <div><x-primary-button>{{ __('ui.save') }}</x-primary-button></div>
                    </form>
                </div>

                <div class="rounded-lg bg-white p-6 shadow-soft">
                    <h2 class="mb-4 text-base font-semibold text-gray-800">{{ __('ui.members.points_settings') }}</h2>
                    <form method="POST" action="{{ route('members.points-settings') }}" class="grid gap-4">
                        @csrf @method('PUT')
                        <div>
                            <x-input-label :value="__('ui.members.earn_amount')" />
                            <x-text-input name="earn_amount" type="number" min="1" :value="$settings['earn_amount']" class="mt-1 block w-full tnum" required />
                            <p class="mt-1 text-xs text-gray-500">{{ __('ui.members.earn_amount_hint') }}</p>
                        </div>
                        <div>
                            <x-input-label :value="__('ui.members.redeem_value')" />
                            <x-text-input name="redeem_value" type="number" step="0.01" min="0" :value="$settings['redeem_value']" class="mt-1 block w-full tnum" required />
                        </div>
                        <div>
                            <x-input-label :value="__('ui.members.expiry_months')" />
                            <x-text-input name="expiry_months" type="number" min="0" max="120" :value="$settings['expiry_months']" class="mt-1 block w-full tnum" required />
                            <p class="mt-1 text-xs text-gray-500">{{ __('ui.members.expiry_hint') }}</p>
                        </div>
                        <div><x-primary-button>{{ __('ui.save') }}</x-primary-button></div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
