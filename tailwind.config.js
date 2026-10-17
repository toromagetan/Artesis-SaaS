import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/**
 * Token desain Lentera (Blueprint F.2.0) — SATU tempat, dipakai semua modul.
 * Emerald pekat = utama; emas = aksen terbatas; Plus Jakarta Sans;
 * radius 8–12px; bayangan maksimal satu tingkat tipis.
 *
 * @type {import('tailwindcss').Config}
 */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['"Plus Jakarta Sans"', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                lentera: {
                    50: '#ecfdf5',
                    100: '#d1fae5',
                    200: '#a7f3d0',
                    300: '#6ee7b7',
                    400: '#34d399',
                    500: '#10b981',
                    600: '#059669',
                    700: '#047857',
                    800: '#065f46',
                    900: '#064e3b',
                    950: '#022c22',
                },
                gold: {
                    light: '#e7ce7a',
                    DEFAULT: '#c9a227',
                    dark: '#9a7b1c',
                },
            },
            boxShadow: {
                // Satu tingkat bayangan tipis — tingkat maksimum yang diizinkan F.2.0.
                soft: '0 1px 2px 0 rgb(2 44 34 / 0.05)',
            },
        },
    },

    plugins: [forms],
};
