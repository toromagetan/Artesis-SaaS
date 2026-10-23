@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-gray-300 focus:border-lentera-500 focus:ring-lentera-500 rounded-md shadow-sm']) }}>
