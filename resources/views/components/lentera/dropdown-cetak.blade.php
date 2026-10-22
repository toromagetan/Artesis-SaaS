@props(['label' => null])
{{-- DropdownCetak (Blueprint F.2.0) — SATU-SATUNYA wadah pilihan dokumen
     per layar. Semua tautan di dalamnya membuka dokumen penuh di tab baru.
     Panel memakai position:fixed yang dihitung dari posisi tombol (flip ke
     atas bila ruang bawah tidak cukup) agar tidak terpotong leluhur
     overflow-hidden seperti kartu tabel — audit tombol Aksi 2026-10-22. --}}
<div x-data="{
        open: false,
        panelStyle: '',
        toggle() {
            this.open = ! this.open;
            if (this.open) this.$nextTick(() => this.place());
        },
        place() {
            const b = this.$refs.btn.getBoundingClientRect();
            const p = this.$refs.panel;
            const prevDisplay = p.style.display;
            const prevVisibility = p.style.visibility;
            p.style.display = 'block';
            p.style.visibility = 'hidden';
            const w = p.offsetWidth;
            const h = p.offsetHeight;
            p.style.display = prevDisplay;
            p.style.visibility = prevVisibility;
            const left = Math.max(8, Math.min(b.right - w, window.innerWidth - w - 8));
            let top = b.bottom + 8;
            if (top + h > window.innerHeight - 8) top = Math.max(8, b.top - h - 8);
            this.panelStyle = 'left:' + left + 'px;top:' + top + 'px;';
        }
     }"
     @scroll.window="open = false" @resize.window="open = false" @keydown.escape.window="open = false"
     class="relative inline-block text-left" data-lentera="dropdown-cetak">
    <button type="button" x-ref="btn" @click="toggle()"
        class="inline-flex items-center gap-1.5 rounded-lg border border-lentera-700 bg-white px-4 py-2 text-sm font-semibold text-lentera-800 shadow-soft transition duration-150 hover:bg-lentera-50 focus:outline-none focus:ring-2 focus:ring-lentera-500">
        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659" /></svg>
        {{ $label ?? __('ui.print') }}
        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
    </button>
    <div x-show="open" x-ref="panel" @click.away="open = false" x-cloak :style="panelStyle"
        class="fixed z-50 w-56 rounded-lg bg-white py-1 shadow-soft ring-1 ring-gray-900/5">
        {{ $slot }}
    </div>
</div>
