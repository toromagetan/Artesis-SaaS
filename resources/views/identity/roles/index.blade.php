<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Peran & Izin</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-md bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm">{{ session('status') }}</div>
            @endif

            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 border-b">
                            <th class="px-4 py-3 font-medium">Peran</th>
                            <th class="px-4 py-3 font-medium">Jumlah Izin</th>
                            <th class="px-4 py-3 font-medium">Jumlah Pengguna</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($roles as $role)
                            <tr class="border-b last:border-0">
                                <td class="px-4 py-3 font-medium text-gray-800">{{ $role->label }} <span class="text-xs text-gray-400">({{ $role->name }})</span></td>
                                <td class="px-4 py-3">{{ $role->permissions_count }}</td>
                                <td class="px-4 py-3">{{ $role->users_count }}</td>
                                <td class="px-4 py-3 text-right">
                                    <div x-data="{ open: false }" class="relative inline-block text-left">
                                        <x-secondary-button type="button" @click="open = !open">Aksi ▾</x-secondary-button>
                                        <div x-show="open" @click.away="open = false" class="absolute right-0 mt-2 w-48 rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5 py-1 z-10">
                                            <a href="{{ route('roles.edit', $role) }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Ubah Izin</a>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
