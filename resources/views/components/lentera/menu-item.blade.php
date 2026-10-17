@props(['danger' => false])
{{-- Item di dalam DropdownAksi/DropdownCetak/RowActions. --}}
<a {{ $attributes->merge(['class' => 'block px-4 py-2 text-sm transition duration-150 hover:bg-lentera-50 '.($danger ? 'text-red-600' : 'text-gray-700')]) }}>{{ $slot }}</a>
