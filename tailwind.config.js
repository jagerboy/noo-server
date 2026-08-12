import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                asw: {
                    red: '#D9232A',
                    'red-dark': '#B91C1C',
                    blue: '#1E2B7B',
                    'blue-light': '#2563EB',
                },
                ina: {
                    purple: '#542B85',
                    'purple-dark': '#3B0764',
                    gold: '#F59E0B',
                    'gold-light': '#FBBF24',
                },
            },
        },
    },

    plugins: [forms],
};
