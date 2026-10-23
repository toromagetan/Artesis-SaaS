<x-app-layout>
    <x-slot name="header">{{ __('ui.backups.title') }}</x-slot>

    <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        @if (session('status'))
            <div class="mb-4 rounded-md border border-lentera-600/40 bg-lentera-50 px-4 py-3 text-sm text-lentera-800">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif

        <div class="mb-4 flex items-center justify-between gap-3">
            <p class="max-w-2xl text-sm text-gray-500">{{ __('ui.backups.help') }}</p>
            <x-lentera.aksi-bar>
                <form method="POST" action="{{ route('backups.store') }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-sm font-medium text-gray-700 shadow-soft transition duration-150 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-lentera-500">{{ __('ui.backups.create') }}</button>
                </form>
            </x-lentera.aksi-bar>
        </div>

        <div class="mb-4 rounded-xl bg-white p-4 shadow-sm">
            <h3 class="text-sm font-semibold text-gray-800">{{ __('ui.backups.offsite') }}</h3>
            <p class="mt-1 max-w-2xl text-sm text-gray-500">{{ __('ui.backups.offsite_help') }}</p>
            <form method="POST" action="{{ route('backups.offsite') }}" class="mt-3 flex flex-wrap items-center gap-3">
                @csrf
                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="aktif" value="1" @checked($offsiteEnabled) class="rounded border-gray-300 text-lentera-600 focus:ring-lentera-500">
                    {{ __('ui.backups.offsite_enable') }}
                </label>
                <button type="submit" class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-sm font-medium text-gray-700 shadow-soft transition duration-150 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-lentera-500">{{ __('ui.backups.offsite_save') }}</button>
            </form>
        </div>

        <div class="overflow-hidden rounded-xl bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-gray-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ __('ui.backups.file') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('ui.backups.size') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('ui.backups.created_col') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($entries as $entry)
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-800">{{ $entry['name'] }}
                                <div class="mt-1">@if ($entry['verified'])<x-lentera.badge tone="green">{{ __('ui.backups.verified') }}</x-lentera.badge>@else<x-lentera.badge tone="red">{{ __('ui.backups.unverified') }}</x-lentera.badge>@endif</div>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ number_format($entry['size'] / 1024, 1) }} KB</td>
                            <td class="px-4 py-3 text-gray-600">{{ date('d M Y H:i', $entry['created_at']) }}</td>
                            <td class="px-4 py-3 text-right">
                                <x-lentera.aksi-baris>
                                    <x-lentera.menu-item :href="route('backups.download', ['berkas' => $entry['name']])" sm>{{ __('ui.backups.download') }}</x-lentera.menu-item>
                                    @if (auth()->user()->hasRole('Owner'))
                                        <x-lentera.aksi-dialog danger sm :label="__('ui.backups.restore')" :title="__('ui.backups.restore').'?'">
                                            <form method="POST" action="{{ route('backups.restore') }}">
                                                @csrf
                                                <input type="hidden" name="berkas" value="{{ $entry['name'] }}">
                                                <label class="block text-left text-xs text-gray-500">{{ __('ui.backups.restore_confirm_label') }}
                                                    <input type="text" name="konfirmasi" required class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                                                </label>
                                                <div class="mt-4 flex justify-end gap-2">
                                                    <button type="button" @click="open = false" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('ui.back') }}</button>
                                                    <button type="submit" class="inline-flex items-center rounded-lg bg-red-600 px-3.5 py-2 text-sm font-semibold text-white hover:bg-red-700">{{ __('ui.backups.restore') }}</button>
                                                </div>
                                            </form>
                                        </x-lentera.aksi-dialog>
                                    @endif
                                    <form method="POST" action="{{ route('backups.destroy') }}" onsubmit="return confirm('{{ __('ui.backups.delete_confirm') }}')">
                                        @csrf
                                        <input type="hidden" name="berkas" value="{{ $entry['name'] }}">
                                        <button type="submit" class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-red-300 bg-white px-2.5 py-1.5 text-[13px] font-medium text-red-600 shadow-soft transition duration-150 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-lentera-500">{{ __('ui.backups.delete') }}</button>
                                    </form>
                                </x-lentera.aksi-baris>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-6">
                                <x-lentera.empty-state>{{ __('ui.backups.empty') }}</x-lentera.empty-state>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
