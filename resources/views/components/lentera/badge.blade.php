@props(['active' => false])
{{-- Badge status aktif/nonaktif (Blueprint F.2.0). --}}
@if ($active)
    <span class="inline-flex items-center rounded-full border border-lentera-200 bg-lentera-50 px-2 py-0.5 text-xs font-semibold text-lentera-700">{{ __('ui.active') }}</span>
@else
    <span class="inline-flex items-center rounded-full border border-gray-200 bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-500">{{ __('ui.inactive') }}</span>
@endif
