{{--
    Header بالای صفحه.
    دکمهٔ hamburger فقط در موبایل و از طریق Alpine event
    سایدبار را باز/بسته می‌کند (شناسهٔ state در elements/sidebar).

    عنوان صفحه از طریق @yield('header_title') از section صفحه می‌آید.
--}}
<header class="flex h-16 shrink-0 items-center justify-between border-b border-line bg-surface-base px-4 sm:px-6">
    <div class="flex items-center gap-3">
        <button type="button"
                x-on:click="$dispatch('toggle-mobile-sidebar')"
                aria-label="باز کردن منوی اصلی"
                class="grid h-10 w-10 place-items-center rounded-ui text-ink-muted transition hover:bg-surface-inset hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-primary md:hidden">
            <i class="fas fa-bars text-lg" aria-hidden="true"></i>
        </button>
        <h2 class="text-lg font-semibold text-ink">@yield('header_title', 'داشبورد')</h2>
    </div>
</header>
