<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center px-4 py-2 bg-lentera-700 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-lentera-800 focus:bg-lentera-800 active:bg-lentera-900 focus:outline-none focus:ring-2 focus:ring-lentera-500 focus:ring-offset-2 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
