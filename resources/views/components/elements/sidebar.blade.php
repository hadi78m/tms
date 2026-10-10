{{--
    Sidebar Shell — فقط chrome و رفتار responsive.
    محتوای navigation از طریق slot تزریق می‌شود:

        <x-elements.sidebar>
            <x-sidebars.main />
        </x-elements.sidebar>

    دسکتاپ: ستون ثابت در سمت راست (RTL) و در جریان layout.
    موبایل: off-canvas از سمت راست با backdrop.

    Phase 5B-1 — مدیریت تمرکز منوی موبایل:
      - با باز شدن (off-canvas) تمرکز وارد پنل می‌شود،
      - Tab و Shift+Tab داخل پنل تله می‌شوند،
      - Escape بستن را حفظ می‌کند (رفتار قبلی)،
      - با بسته شدن، تمرکز به عنصر فراخواننده (دکمهٔ همبرگری) برمی‌گردد،
      - در حالت دسکتاپ سایدبار کاملاً ثابت (static) می‌ماند، نه تله‌ای دارد و
        نه inert می‌شود؛ همچنین در موبایلِ بسته، پنلِ off-canvas با inert از
        ترتیب Tab خارج می‌شود.
--}}
<div
    x-data="{
        open: false,
        isMobile: false,
        opener: null,
        init() {
            const query = window.matchMedia('(max-width: 767px)');
            this.isMobile = query.matches;
            query.addEventListener('change', (event) => {
                this.isMobile = event.matches;
                if (! event.matches) {
                    this.open = false;
                }
            });
        },
        focusables() {
            const panel = this.$refs.sidebarPanel;

            if (! panel) {
                return [];
            }

            const selector = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex=\'-1\'])';

            return Array.from(panel.querySelectorAll(selector))
                .filter((el) => el.offsetWidth > 0 || el.offsetHeight > 0);
        },
        activate() {
            this.opener = document.activeElement;
            this.open = true;
        },
        deactivate() {
            this.open = false;
        },
        toggle() {
            this.open ? this.deactivate() : this.activate();
        },
        focusFirst() {
            const items = this.focusables();
            (items[0] ?? this.$refs.sidebarPanel)?.focus();
        },
        trapFocus(event) {
            if (! this.open || ! this.isMobile) {
                return;
            }

            const items = this.focusables();

            if (items.length === 0) {
                event.preventDefault();
                this.$refs.sidebarPanel?.focus();
                return;
            }

            const first = items[0];
            const last = items[items.length - 1];
            const current = document.activeElement;

            if (! items.includes(current)) {
                event.preventDefault();
                (event.shiftKey ? last : first).focus();
                return;
            }

            if (event.shiftKey && current === first) {
                event.preventDefault();
                last.focus();
                return;
            }

            if (! event.shiftKey && current === last) {
                event.preventDefault();
                first.focus();
            }
        },
    }"
    x-init="$watch('open', value => {
        if (value) {
            document.body.classList.add('overflow-y-hidden');
            $nextTick(() => focusFirst());
        } else {
            document.body.classList.remove('overflow-y-hidden');
            const target = opener;
            opener = null;
            $nextTick(() => {
                if (target && typeof target.focus === 'function' && document.contains(target)) {
                    target.focus();
                }
            });
        }
    })"
    x-on:toggle-mobile-sidebar.window="toggle()"
    x-on:close-mobile-sidebar.window="deactivate()"
    x-on:keydown.escape.window="deactivate()"
>
    <aside
        x-ref="sidebarPanel"
        tabindex="-1"
        x-on:keydown.tab="trapFocus($event)"
        :inert="isMobile && ! open"
        class="fixed inset-y-0 right-0 z-50 flex w-64 max-w-[85vw] translate-x-full flex-col border-e border-line bg-surface-base shadow-ui-lift transition-transform duration-300 ease-out md:static md:z-auto md:max-w-none md:shrink-0 md:translate-x-0 md:shadow-none md:transition-none"
        :class="open ? '!translate-x-0' : ''"
    >
        {{-- دکمهٔ بستن — فقط موبایل، در لبهٔ آزاد پنل (سمت چپ) --}}
        <button type="button"
                x-ref="closeButton"
                x-on:click="deactivate()"
                aria-label="بستن منو"
                class="absolute left-3 top-3 grid h-9 w-9 place-items-center rounded-ui text-ink-muted transition hover:bg-surface-inset hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-primary md:hidden">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>

        {{ $slot }}
    </aside>

    {{-- Backdrop — فقط موبایل --}}
    <div
        x-show="open"
        x-on:click="deactivate()"
        style="display: none;"
        aria-hidden="true"
        class="fixed inset-0 z-40 bg-ink/45 md:hidden"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
    ></div>
</div>
