@props(['label', 'danger' => false, 'title' => null, 'sm' => false])
{{-- AksiDialog (ADR-018) — tombol perintah yang membutuhkan isian
     (alasan/konfirmasi/dll.): tombolnya terlihat sejajar seperti perintah
     lain; isian dibuka lewat dialog. Slot berisi <form> aksinya.
     Dialog hanya untuk aksi — tidak pernah untuk menampilkan detail. --}}
<div x-data="{ open: false }" class="inline-block">
    <button type="button" @click="open = true"
        class="inline-flex items-center gap-1.5 rounded-lg shadow-soft transition duration-150 focus:outline-none focus:ring-2 focus:ring-lentera-500 {{ $sm ? 'px-2.5 py-1.5 text-[13px] ' : 'px-3.5 py-2 text-sm ' }}{{ $danger ? 'border border-red-300 bg-white font-medium text-red-600 hover:bg-red-50' : 'border border-gray-300 bg-white font-medium text-gray-700 hover:bg-gray-50' }}">
        {{ $label }}
    </button>
    <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-gray-900/50 p-4 sm:items-center" @keydown.escape.window="open = false">
        <div class="w-full max-w-md rounded-xl bg-white p-5 text-left shadow-soft" @click.away="open = false">
            @if($title)
                <h3 class="mb-3 text-base font-semibold text-gray-900">{{ $title }}</h3>
            @endif
            {{ $slot }}
        </div>
    </div>
</div>
