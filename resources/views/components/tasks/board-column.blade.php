@props([
    'column' => null,
    'label' => null,
    'tasks' => null,
    'variant' => 'default',
    'collapsible' => false,
    // Phase 5B-1 (§P): a collapsible column may START collapsed.
    'collapsed' => false,
])

@php
$count = $tasks instanceof \Illuminate\Support\Collection ? $tasks->count() : 0;
$id = 'board-column-'.$column;
// حالت اولیهٔ جمع‌شده هم سمت سرور رندر می‌شود تا پیش از اجرای Alpine هم
// صفحه درست دیده شود (progressive enhancement)؛ Alpine فقط آن را تغییر می‌دهد.
$collapsed = (bool) $collapsed;
@endphp

<section id="{{ $id }}"
         aria-labelledby="{{ $id }}-title"
         x-data="{ collapsed: @js($collapsed) }"
         class="flex min-h-0 flex-col rounded-ui-md border border-line bg-surface-inset/60">
    <header class="flex items-center justify-between gap-2 border-b border-line-subtle px-3.5 py-3">
        <div class="flex items-center gap-2">
            <x-ui.status-indicator :variant="$variant" size="sm" :dot="true" />
            <h3 id="{{ $id }}-title" class="text-sm font-extrabold text-ink">{{ $label }}</h3>
        </div>

        <div class="flex items-center gap-2">
            {{-- Real count from the filtered query (gate §12) --}}
            <span class="inline-flex min-w-[1.5rem] items-center justify-center rounded-full bg-surface-base px-1.5 py-0.5 text-[11px] font-bold text-ink-muted ring-1 ring-inset ring-line">
                {{ $count }}
            </span>

            @if ($collapsible)
                <button type="button"
                        aria-expanded="{{ $collapsed ? 'false' : 'true' }}"
                        :aria-expanded="collapsed ? 'false' : 'true'"
                        aria-controls="{{ $id }}-body"
                        class="grid h-6 w-6 place-items-center rounded-ui text-ink-subtle transition hover:bg-surface-base hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                        x-on:click="collapsed = ! collapsed">
                    <svg class="h-3.5 w-3.5 transition-transform"
                         :class="collapsed ? '-rotate-90' : ''"
                         fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M19 9l-7 7-7-7" />
                    </svg>
                    <span class="sr-only">باز/بسته کردن ستون {{ $label }}</span>
                </button>
            @endif
        </div>
    </header>

    <div x-ref="body"
         id="{{ $id }}-body"
         x-show="! collapsed"
         @if ($collapsed) style="display: none;" @endif
         class="flex-1 space-y-2.5 p-2.5">
        @forelse($tasks as $task)
            <x-tasks.task-card :task="$task" />
        @empty
            {{-- Column empty state (gate §17) — Phase 1 component --}}
            <x-ui.empty-state title="تسکی در این مرحله وجود ندارد" />
        @endforelse
    </div>
</section>
