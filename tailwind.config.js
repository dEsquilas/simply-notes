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
                main1: '#032a33',
                main2: '#255965',
                main3: '#4c6f7b',
                main4: '#f1d18a',

                cblack: '#212121',
                cgray: '#374151',
                cgrey: '#BDBDBD',
                cwhite: '#ffffff',
            },
        },
    },

    plugins: [forms],
};
