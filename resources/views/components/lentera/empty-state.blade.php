{{-- EmptyState (Blueprint F.2.0): keadaan kosong yang menjelaskan + aksi buat. --}}
<div class="px-4 py-10 text-center" data-lentera="empty-state">
    <p class="text-sm text-gray-400">{{ $slot }}</p>
    @isset($action)
        <div class="mt-3">{{ $action }}</div>
    @endisset
</div>
