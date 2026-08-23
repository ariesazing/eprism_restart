import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
                // Formal serif for page/section headings — the letterhead-and-certificate
                // pairing common to DepEd/government documents, set alongside Figtree body
                // text rather than replacing it everywhere.
                serif: ['Lora', ...defaultTheme.fontFamily.serif],
            },
            colors: {
                // The school division's cherry-red brand color, promoted to a real token
                // (previously scattered as literal red-700/red-800/raw-hex classes) so it's
                // defined once and consistent everywhere it's used.
                cherry: {
                    50: '#fdf2f3',
                    100: '#fbe3e5',
                    200: '#f6c7cc',
                    300: '#ed9ca6',
                    400: '#de6779',
                    500: '#c93f53',
                    600: '#a9233a',
                    700: '#8c1730',
                    800: '#6e0f26',
                    900: '#591020',
                    950: '#33060f',
                },
            },
        },
    },

    plugins: [forms],
};
