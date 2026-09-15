import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: "class",
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.vue',
        './vendor/hadii/laramina/resources/views/**/*.blade.php',
        './public/js/**/*.js',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['IRANSans', 'Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                primary: '#4F46E5',
                'primary-hover': '#4338CA',
                secondary: '#06B6D4',
            }
        },
    },

    plugins: [forms],
};
