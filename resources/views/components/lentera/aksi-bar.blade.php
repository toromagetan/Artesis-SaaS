{{-- AksiBar (ADR-018) — kelompok tombol perintah SEJAJAR di kepala layar:
     seluruh perintah tampil sebagai tombol tersendiri yang selalu terlihat
     (menggantikan DropdownAksi + DropdownCetak). "Buat Baru" = tombol primer
     pertama; dokumen = tombol print; berbahaya terakhir + merah. --}}
<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-2']) }} data-lentera="aksi-bar">{{ $slot }}</div>
