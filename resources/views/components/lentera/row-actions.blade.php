{{-- Dropdown aksi per baris tabel — tepat satu per baris (Blueprint F.2 butir 8).
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
     class="relative inline-block text-left" data-lentera="row-actions">
    <button type="button" x-ref="btn" @click="toggle()"
        class="inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 shadow-soft transition duration-150 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-lentera-500">
        {{ __('ui.actions') }}
        <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
    </button>
    <div x-show="open" x-ref="panel" @click.away="open = false" x-cloak :style="panelStyle"
        class="fixed z-50 w-52 rounded-lg bg-white py-1 shadow-soft ring-1 ring-gray-900/5">
        {{ $slot }}
    </div>
</div>
