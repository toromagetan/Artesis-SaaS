<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.offline.devices_title') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-lg border border-lentera-200 bg-lentera-50 px-4 py-3 text-sm text-lentera-800">{{ session('status') }}</div>
            @endif
            @if (session('plain_token'))
                <div class="rounded-lg border border-gold/50 bg-gold/10 px-4 py-3 text-sm text-gold-dark">
                    <p class="font-medium">{{ __('ui.offline.plain_once', ['name' => session('device_name')]) }}</p>
                    <code class="mt-1 block break-all rounded bg-white px-3 py-2 font-mono text-sm text-gray-800">{{ session('plain_token') }}</code>
                </div>
            @endif

            <div class="rounded-lg bg-white p-6 shadow-soft">
                <h2 class="font-semibold text-gray-800">{{ __('ui.offline.register') }}</h2>
                <form method="POST" action="{{ route('offline-devices.store') }}" class="mt-4 grid gap-4 sm:grid-cols-3">
                    @csrf
                    <div>
                        <x-input-label for="name" :value="__('ui.offline.name')" />
                        <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name')" required maxlength="100" placeholder="KSR1" />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="branch_id" :value="__('ui.offline.branch')" />
                        <select id="branch_id" name="branch_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500" required>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}" @selected(old('branch_id') == $branch->id)>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="user_id" :value="__('ui.offline.cashier')" />
                        <select id="user_id" name="user_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500" required>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}" @selected(old('user_id') == $user->id)>{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-3 flex justify-end">
                        <x-primary-button>{{ __('ui.offline.register') }}</x-primary-button>
                    </div>
                </form>
                <p class="mt-3 text-xs text-gray-400">{{ __('ui.offline.hint') }}</p>
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.offline.name') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.offline.branch') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.offline.cashier') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.offline.last_sync') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.offline.status') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($devices as $device)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 font-medium text-gray-800">{{ $device->name }}</td>
                                <td class="px-4 py-3">{{ $device->branch->name }}</td>
                                <td class="px-4 py-3">{{ $device->user->name }}</td>
                                <td class="px-4 py-3 tnum">{{ $device->last_sync_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    @if ($device->revoked_at)
                                        <span class="text-red-600">{{ __('ui.offline.revoked_label') }}</span>
                                    @else
                                        <span class="text-lentera-700">{{ __('ui.active') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @unless ($device->revoked_at)
                                        <x-lentera.aksi-baris>
                                            <form method="POST" action="{{ route('offline-devices.revoke', $device) }}" onsubmit="return confirm('{{ __('ui.offline.revoke_confirm') }}')">
                                                @csrf
                                                <button type="submit" class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-red-300 bg-white px-2.5 py-1.5 text-[13px] font-medium text-red-600 shadow-soft transition duration-150 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-lentera-500">{{ __('ui.offline.revoke') }}</button>
                                            </form>
                                        </x-lentera.aksi-baris>
                                    @endunless
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><x-lentera.empty-state>{{ __('ui.offline.empty') }}</x-lentera.empty-state></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
