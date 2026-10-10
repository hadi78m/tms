import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/**
 * توکن‌های طراحی رابط کاربری TMS (مرجع یکپارچه طراحی)
 *
 * این فایل تنها منبع حقیقت توکن‌های بصری است. لطفاً برای رنگ‌ها،
 * شعاع‌ها، سایه‌ها و فاصله‌ها به جای مقادیر پراکنده از همین
 * نام‌گذاری استفاده کنید:
 *
 *      border-line / border-line-subtle / border-line-strong
 *      bg-surface-page / bg-surface-base / bg-surface-inset
 *      text-ink / text-ink-muted / text-ink-subtle
 *      bg-success-100 text-success-700 ... (success/warning/danger/info)
 *
 * primary/secondary به دلایل سازگاری با کدهای فعلی دست نخورده‌اند.
 */
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
                sans: ['Vazirmatn', 'IRANSans', 'Figtree', ...defaultTheme.fontFamily.sans],
                mono: [...defaultTheme.fontFamily.mono],
            },
            colors: {
                primary: '#4F46E5',
                'primary-hover': '#4338CA',
                secondary: '#06B6D4',

                // خطوط — سیستم border منسجم
                line: {
                    DEFAULT: '#e2e8f0',
                    subtle: '#eef2f7',
                    strong: '#cbd5e1',
                },

                // سطوح — پس‌زمینه‌ها
                surface: {
                    page: '#f8fafc',
                    base: '#ffffff',
                    inset: '#f1f5f9',
                },

                // متن
                ink: {
                    DEFAULT: '#0f172a',
                    muted: '#64748b',
                    subtle: '#94a3b8',
                    invert: '#f8fafc',
                },

                success: {
                    50: '#f0fdf4',
                    100: '#dcfce7',
                    200: '#bbf7d0',
                    300: '#86efac',
                    400: '#4ade80',
                    500: '#22c55e',
                    600: '#16a34a',
                    700: '#15803d',
                    800: '#166534',
                    900: '#14532d',
                },
                warning: {
                    50: '#fffbeb',
                    100: '#fef3c7',
                    200: '#fde68a',
                    300: '#fcd34d',
                    400: '#fbbf24',
                    500: '#f59e0b',
                    600: '#d97706',
                    700: '#b45309',
                    800: '#92400e',
                    900: '#78350f',
                },
                danger: {
                    50: '#fef2f2',
                    100: '#fee2e2',
                    200: '#fecaca',
                    300: '#fca5a5',
                    400: '#f87171',
                    500: '#ef4444',
                    600: '#dc2626',
                    700: '#b91c1c',
                    800: '#991b1b',
                    900: '#7f1d1d',
                },
                info: {
                    50: '#eff6ff',
                    100: '#dbeafe',
                    200: '#bfdbfe',
                    300: '#93c5fd',
                    400: '#60a5fa',
                    500: '#3b82f6',
                    600: '#2563eb',
                    700: '#1d4ed8',
                    800: '#1e40af',
                    900: '#1e3a8a',
                },
            },
            borderRadius: {
                'ui-sm': '0.375rem',
                'ui': '0.5rem',
                'ui-md': '0.75rem',
                'ui-lg': '1rem',
                'ui-xl': '1.5rem',
            },
            boxShadow: {
                'ui-soft': '0 1px 2px rgba(15, 23, 42, 0.05), 0 4px 12px -4px rgba(15, 23, 42, 0.10)',
                'ui-lift': '0 2px 4px rgba(15, 23, 42, 0.06), 0 12px 28px -10px rgba(15, 23, 42, 0.20)',
            },
        },
    },
    plugins: [forms],
};
