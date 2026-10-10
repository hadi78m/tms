@props([
    'name' => null,
    'show' => false,
    'side' => 'left',
    // عرض در دسکتاپ — پیشوند sm: ضروری است تا روی موبایل Drawer تمام‌صفحه شود.
    'width' => 'sm:max-w-md',
    'label' => null,
])

@php
// Shell عمومی — بدون هیچ منطق دامنه‌ای. محتوا از طریق slot پر می‌شود.
$side = in_array($side, ['left', 'right'], true) ? $side : 'left';

$panelPosition = $side === 'left' ? 'inset-y-0 left-0' : 'inset-y-0 right-0';
$enterFrom = $side === 'left' ? '-translate-x-full' : 'translate-x-full';
$leaveTo = $enterFrom;

// لبهٔ آزادِ پنل جای دکمهٔ بستن است (در RTL برعکس می‌شود).
$closePosition = $side === 'left' ? 'start-3' : 'end-3';
@endphp

{{--
    Phase 5B-1 — مدیریت تمرکز (focus management).

    یک Drawerِ باز یک دیالوگ مودال است: تمرکز باید وارد شود، داخل بماند
    (Tab و Shift+Tab) و هنگام بستن به عنصر فراخواننده برگردد.

    رفتارهای موجود دست‌نخورده مانده‌اند:
      - بستن با Escape (keydown.escape.window)
      - بستن با کلیک روی backdrop
      - قفل اسکرول بدنه در زمان باز بودن

    پیاده‌سازی فقط با همان Alpine موجود است — هیچ وابستگی جدیدی اضافه نشده.
--}}
<div
    x-data="{
        show: @js($show),
        opener: null,
        focusables() {
            const panel = this.$refs.drawerPanel;

            if (! panel) {
                return [];
            }

            const selector = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex=\'-1\'])';

            return Array.from(panel.querySelectorAll(selector))
                .filter((el) => el.offsetWidth > 0 || el.offsetHeight > 0);
        },
        activate() {
            this.opener = document.activeElement;
            this.show = true;
        },
        deactivate() {
            this.show = false;
        },
        focusFirst() {
            const items = this.focusables();
            (items[0] ?? this.$refs.drawerPanel)?.focus();
        },
        trapFocus(event) {
            const items = this.focusables();

            if (items.length === 0) {
                event.preventDefault();
                this.$refs.drawerPanel?.focus();
                return;
            }

            const first = items[0];
            const last = items[items.length - 1];
            const current = document.activeElement;

            // تمرکز روی خود پنل (نه داخل لیست) — به ابتدا/انتهای پنل هدایت شود.
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
    x-init="$watch('show', (value) => {
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
    @if (isset($name))
        x-on:open-drawer.window="$event.detail === '{{ $name }}' ? activate() : null"
        x-on:close-drawer.window="$event.detail === '{{ $name }}' ? deactivate() : null"
    @endif
    x-on:close.stop="deactivate()"
    x-on:keydown.escape.window="deactivate()"
    x-show="show"
    class="fixed inset-0 z-50"
    style="display: {{ $show ? 'block' : 'none' }};"
>
    {{-- Backdrop --}}
    <div
        x-show="show"
        x-on:click="deactivate()"
        class="fixed inset-0 bg-ink/45 backdrop-blur-[2px]"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
    ></div>

    {{-- Panel --}}
    <div
        x-show="show"
        x-ref="drawerPanel"
        tabindex="-1"
        x-on:keydown.tab="trapFocus($event)"
        class="fixed {{ $panelPosition }} flex w-full flex-col overflow-hidden bg-surface-base shadow-ui-lift outline-none {{ $width }}"
        x-transition:enter="transform transition ease-out duration-300"
        x-transition:enter-start="{{ $enterFrom }}"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transform transition ease-in duration-200"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="{{ $leaveTo }}"
    >
        {{-- دکمهٔ بستن --}}
        <button type="button"
                x-on:click="deactivate()"
                @if (isset($label)) aria-label="{{ $label }}" @else aria-label="بستن" @endif
                class="absolute top-3 {{ $closePosition }} z-10 grid h-9 w-9 place-items-center rounded-ui border border-line bg-surface-base text-ink-muted transition hover:rotate-90 hover:border-danger-300 hover:text-danger-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>

        {{ $slot }}
    </div>
</div>
