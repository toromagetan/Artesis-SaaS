{{-- AksiBaris (ADR-018) — deretan tombol kecil sejajar untuk aksi per baris
     tabel (menggantikan dropdown RowActions). Item di dalamnya memakai
     MenuItem/tombol ukuran kecil (sm). --}}
<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center justify-end gap-1.5']) }} data-lentera="aksi-baris">{{ $slot }}</div>
