<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Pengguna</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-md bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm">{{ session('status') }}</div>
            @endif

            <div class="flex items-center justify-between mb-4">
                <form method="GET" class="flex gap-2">
                    <x-text-input name="q" :value="$q" placeholder="Cari nama, username, email…" class="w-72" />
                    <x-secondary-button type="submit">Cari</x-secondary-button>
                </form>
                <div x-data="{ open: false }" class="relative">
                    <x-primary-button type="button" @click="open = !open">Aksi ▾</x-primary-button>
                    <div x-show="open" @click.away="open = false" class="absolute right-0 mt-2 w-48 rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5 py-1 z-10">
                        <a href="{{ route('users.create') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Buat Pengguna</a>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 border-b">
                            <th class="px-4 py-3 font-medium">Nama</th>
                            <th class="px-4 py-3 font-medium">Username</th>
                            <th class="px-4 py-3 font-medium">Peran</th>
                            <th class="px-4 py-3 font-medium">Cabang</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $user)
                            <tr class="border-b last:border-0">
                                <td class="px-4 py-3 font-medium text-gray-800">{{ $user->name }}@if ($user->is_platform) <span class="text-xs text-gray-400">(platform)</span>@endif</td>
                                <td class="px-4 py-3">{{ $user->username }}</td>
                                <td class="px-4 py-3">{{ $user->roles->pluck('label')->join(', ') ?: '—' }}</td>
                                <td class="px-4 py-3">{{ $user->hasGlobalAccess() ? 'Semua cabang' : ($user->branches->pluck('name')->join(', ') ?: '—') }}</td>
                                <td class="px-4 py-3">
                                    @if ($user->is_active)
                                        <span class="text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-full px-2 py-0.5 text-xs font-semibold">Aktif</span>
                                    @else
                                        <span class="text-gray-500 bg-gray-100 border border-gray-200 rounded-full px-2 py-0.5 text-xs font-semibold">Nonaktif</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div x-data="{ open: false }" class="relative inline-block text-left">
                                        <x-secondary-button type="button" @click="open = !open">Aksi ▾</x-secondary-button>
                                        <div x-show="open" @click.away="open = false" class="absolute right-0 mt-2 w-48 rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5 py-1 z-10">
                                            <a href="{{ route('users.edit', $user) }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Ubah</a>
                                            <form method="POST" action="{{ route('users.toggle-active', $user) }}">
                                                @csrf
                                                <button type="submit" class="block w-full text-left px-4 py-2 text-sm {{ $user->is_active ? 'text-red-600' : 'text-emerald-700' }} hover:bg-gray-50">{{ $user->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                            </form>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">Belum ada pengguna.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $users->links() }}</div>
        </div>
    </div>
</x-app-layout>
