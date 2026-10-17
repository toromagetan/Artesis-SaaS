@props(['label' => null])
{{-- DropdownAksi (Blueprint F.2.0) — SATU-SATUNYA wadah perintah per layar.
     Item pertama pada layar daftar = "Buat Baru"; item berbahaya di kelompok
     bawah dengan pemisah + merah + konfirmasi (di sisi pemakai). --}}
<div x-data="{ open: false }" class="relative inline-block text-left" data-lentera="dropdown-aksi">
    <button type="button" @click="open = !open"
        class="inline-flex items-center gap-1.5 rounded-lg bg-lentera-700 px-4 py-2 text-sm font-semibold text-white shadow-soft transition duration-150 hover:bg-lentera-800 focus:outline-none focus:ring-2 focus:ring-lentera-500">
        {{ $label ?? __('ui.actions') }}
        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
    </button>
    <div x-show="open" @click.away="open = false" x-cloak
        class="absolute right-0 z-20 mt-2 w-56 rounded-lg bg-white py-1 shadow-soft ring-1 ring-gray-900/5">
        {{ $slot }}
    </div>
</div>
