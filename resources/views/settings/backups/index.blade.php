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
            <x-lentera.dropdown-aksi>
                <form method="POST" action="{{ route('backups.store') }}">
                    @csrf
                    <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-lentera-50">{{ __('ui.backups.create') }}</button>
                </form>
            </x-lentera.dropdown-aksi>
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
                            <td class="px-4 py-3 font-medium text-gray-800">{{ $entry['name'] }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ number_format($entry['size'] / 1024, 1) }} KB</td>
                            <td class="px-4 py-3 text-gray-600">{{ date('d M Y H:i', $entry['created_at']) }}</td>
                            <td class="px-4 py-3 text-right">
                                <x-lentera.dropdown-aksi>
                                    <x-lentera.menu-item :href="route('backups.download', ['berkas' => $entry['name']])">{{ __('ui.backups.download') }}</x-lentera.menu-item>
                                    @if (auth()->user()->hasRole('Owner'))
                                        <form method="POST" action="{{ route('backups.restore') }}" class="border-t border-gray-100 px-4 py-2">
                                            @csrf
                                            <input type="hidden" name="berkas" value="{{ $entry['name'] }}">
                                            <label class="block text-left text-xs text-gray-500">{{ __('ui.backups.restore_confirm_label') }}
                                                <input type="text" name="konfirmasi" required class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                                            </label>
                                            <button type="submit" class="mt-2 block w-full px-2 py-1 text-left text-sm text-red-600 hover:bg-red-50">{{ __('ui.backups.restore') }}</button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('backups.destroy') }}" onsubmit="return confirm('{{ __('ui.backups.delete_confirm') }}')">
                                        @csrf
                                        <input type="hidden" name="berkas" value="{{ $entry['name'] }}">
                                        <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50">{{ __('ui.backups.delete') }}</button>
                                    </form>
                                </x-lentera.dropdown-aksi>
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
