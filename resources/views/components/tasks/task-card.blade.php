@props([
    'task' => null,
])

@use('App\Support\TaskBoard\BoardColumns')

@php
$status = (string) $task->status;
$view = BoardColumns::statusView($status);
$tier = BoardColumns::reviewTier($status);
$isRework = $status === 'needs_rework';
$assignee = $task->activeAssignment?->user;
$dueLabel = $task->planned_due_at ? jdate($task->planned_due_at)->format('Y/m/d') : null;
@endphp

<article class="group relative rounded-ui-md border {{ $isRework ? 'border-danger/60 bg-danger-50/40' : 'border-line bg-surface-base' }} p-3.5 shadow-ui-soft transition-all duration-200 hover:-translate-y-0.5 hover:shadow-ui-lift focus-within:shadow-ui-lift">
    <div class="flex items-start justify-between gap-2">
        <h4 class="text-sm font-bold leading-snug text-ink">
            {{-- Phase 4 (gate §20): the card OPENS THE DRAWER on click. The
                 anchor is kept as the real href so the interaction stays a
                 genuine link for keyboards and no-JS (progressive
                 enhancement); Alpine intercepts a plain left-click and
                 fetches ?view=drawer instead of navigating. Modified clicks
                 (ctrl/cmd/middle) fall through to the browser. --}}
            <a href="{{ route('tasks.show', $task->id) }}"
               data-drawer-url="{{ route('tasks.show', ['task' => $task->id, 'view' => 'drawer']) }}"
               x-on:click="if ($event.metaKey || $event.ctrlKey || $event.shiftKey || $event.button !== 0) { return; } $event.preventDefault(); $el.focus(); $dispatch('open-task-drawer', $el.dataset.drawerUrl)"
               class="outline-none after:absolute after:inset-0 after:content-[''] focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-surface-base rounded-ui-md"
               aria-label="جزئیات وظیفهٔ {{ $task->title }}">
                {{ $task->title }}
            </a>
        </h4>
        <span class="shrink-0 font-mono text-[10px] text-ink-subtle" dir="ltr">#{{ $task->id }}</span>
    </div>

    {{-- Status: badge + dot indicator. Text always carries the meaning; the
         color variant is decorative only (gate §24/§25). --}}
    <div class="mt-2.5 flex flex-wrap items-center gap-1.5">
        <x-ui.badge :variant="$view['variant']" dot size="sm">{{ $view['label'] }}</x-ui.badge>

        @if ($isRework)
            {{-- needs_rework visual distinction (gate §9): badge + icon + text,
                 never color alone. No rework action/counter is created. --}}
            <span class="inline-flex items-center gap-1 rounded-full bg-danger-100 px-2 py-0.5 text-[11px] font-bold text-danger-700 ring-1 ring-inset ring-danger-200">
                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                </svg>
                بازگشت خورده
            </span>
        @endif
    </div>

    {{-- Review Tier (gate §8): whose action the task is waiting on. --}}
    @if ($tier !== null)
        <p class="mt-2 inline-flex items-center gap-1.5 rounded-ui bg-warning-50 px-2 py-1 text-[11px] font-medium text-warning-800 ring-1 ring-inset ring-warning-200">
            <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            {{ $tier }}
        </p>
    @endif

    {{-- Project / Module-Stage (if available) --}}
    <dl class="mt-3 space-y-1 text-xs text-ink-muted">
        <div class="flex items-center gap-1.5">
            <svg class="h-3.5 w-3.5 shrink-0 text-ink-subtle" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
            </svg>
            <span class="truncate">{{ $task->project?->name ?? '-' }}</span>
        </div>
        @if ($task->moduleStage)
            <div class="flex items-center gap-1.5">
                <svg class="h-3.5 w-3.5 shrink-0 text-ink-subtle" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7" />
                </svg>
                <span class="truncate">{{ $task->moduleStage->name }}</span>
            </div>
        @endif
    </dl>

    {{-- Footer: assignee, due date, counts --}}
    <div class="mt-3 flex items-center justify-between gap-2 border-t border-line-subtle pt-2.5">
        <div class="flex min-w-0 items-center gap-1.5">
            @if ($assignee)
                <x-ui.avatar :name="$assignee->name" size="xs" />
                <span class="truncate text-[11px] font-medium text-ink-muted">{{ $assignee->name }}</span>
            @else
                <span class="inline-flex items-center gap-1 text-[11px] text-ink-subtle">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    بدون مسئول
                </span>
            @endif
        </div>

        <div class="flex shrink-0 items-center gap-2 text-[11px] text-ink-subtle">
            @if ($dueLabel)
                <span class="inline-flex items-center gap-1 font-mono" dir="ltr" title="مهلت انجام">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    {{ $dueLabel }}
                </span>
            @endif

            @if ($task->dependencies_as_successor_count > 0)
                <span class="inline-flex items-center gap-1" title="تعداد پیش‌نیازها">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7" />
                    </svg>
                    {{ $task->dependencies_as_successor_count }}
                </span>
            @endif

            @if ($task->documents_count > 0)
                <span class="inline-flex items-center gap-1" title="تعداد مستندات">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                    </svg>
                    {{ $task->documents_count }}
                </span>
            @endif
        </div>
    </div>
</article>
