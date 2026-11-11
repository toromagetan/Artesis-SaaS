<x-app-layout>
    <x-slot name="header">{{ __('ui.tenant_exports.title') }}</x-slot>

    <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        @if (session('status'))
            <div class="mb-4 rounded-md border border-lentera-600/40 bg-lentera-50 px-4 py-3 text-sm text-lentera-800">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif

        @if ($tenant === null)
            {{-- Pemilih tenant untuk aktor platform. --}}
            <p class="mb-3 text-sm text-gray-500">{{ __('ui.tenant_exports.pick_tenant') }}</p>
            <div class="overflow-hidden rounded-xl bg-white shadow-sm">
                <ul class="divide-y divide-gray-100">
                    @foreach ($tenants as $candidate)
                        <li>
                            <a href="{{ route('tenant-exports.index', ['tenant_id' => $candidate->id]) }}" class="block px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ $candidate->name }}</a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @else
            <div class="mb-4 flex items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-medium text-gray-700">{{ __('ui.tenant_exports.for_tenant', ['name' => $tenant->name]) }}</p>
                    <p class="mt-1 max-w-2xl text-sm text-gray-500">{{ __('ui.tenant_exports.help') }}</p>
                </div>
                <x-lentera.aksi-bar>
                    <form method="POST" action="{{ route('tenant-exports.store') }}">
                        @csrf
                        @if (auth()->user()->is_platform)
                            <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
                        @endif
                        <button type="submit" class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-sm font-medium text-gray-700 shadow-soft transition duration-150 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-lentera-500">{{ __('ui.tenant_exports.create') }}</button>
                    </form>
                </x-lentera.aksi-bar>
            </div>

            <div class="overflow-hidden rounded-xl bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-gray-500">
                        <tr>
                            <th class="px-4 py-3 font-medium">{{ __('ui.backups.file') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.backups.size') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.backups.created_col') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.backups.verified') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($entries as $entry)
                            <tr>
                                <td class="px-4 py-3 font-medium text-gray-700">{{ $entry['name'] }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ number_format($entry['size'] / 1024, 1) }} KB</td>
                                <td class="px-4 py-3 text-gray-500">{{ date('Y-m-d H:i', $entry['created_at']) }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $entry['verified'] ? __('ui.backups.verified') : __('ui.backups.unverified') }}</td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('tenant-exports.download', array_merge(['berkas' => $entry['name']], auth()->user()->is_platform ? ['tenant_id' => $tenant->id] : [])) }}" class="text-lentera-700 hover:underline">{{ __('ui.backups.download') }}</a>
                                    <form method="POST" action="{{ route('tenant-exports.destroy') }}" class="ml-3 inline" onsubmit="return confirm('{{ __('ui.tenant_exports.delete_confirm') }}')">
                                        @csrf
                                        <input type="hidden" name="berkas" value="{{ $entry['name'] }}">
                                        @if (auth()->user()->is_platform)
                                            <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
                                        @endif
                                        <button type="submit" class="text-red-600 hover:underline">{{ __('ui.backups.delete') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-6 text-center text-gray-400">{{ __('ui.tenant_exports.empty') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-app-layout>
