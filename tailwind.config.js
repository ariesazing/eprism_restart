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
                // Primary red for research actions and navigation.
                cherry: {
                    50: '#fff1ed', 100: '#ffe1da', 200: '#ffc6bc',
                    300: '#ffa091', 400: '#f76c5e', 500: '#e54238',
                    600: '#d22a30', 700: '#bc202c', 800: '#951923',
                    900: '#7b1d24', 950: '#450d13',
                },
                // Complementary yellow for attention, highlights and secondary controls.
                gold: {
                    50: '#fffbee', 100: '#fff3c2', 200: '#ffe991',
                    300: '#ffdc58', 400: '#f7ce40', 500: '#e9b727',
                    600: '#be8918', 700: '#8e6415', 800: '#76511a',
                    900: '#614318', 950: '#38240a',
                },
            },
        },
    },

    plugins: [forms],
};
