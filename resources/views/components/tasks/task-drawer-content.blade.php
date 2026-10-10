@use('App\Domain\Enums\TaskPriority')
@use('App\Support\TaskBoard\BoardColumns')

@php
$status = (string) $task->status;
// اولویت — فقط از منبع واحد ارائه در enum؛ نگاشت تکراری در ویو نداریم.
$priorityView = TaskPriority::viewOf($task->priority);
$statusView = BoardColumns::statusView($status);
$tier = BoardColumns::reviewTier($status);
$isRework = $status === 'needs_rework';
$assignee = $task->activeAssignment?->user;
$createdLabel = jdate($task->created_at)->format('Y/m/d');
$dueLabel = $task->planned_due_at ? jdate($task->planned_due_at)->format('Y/m/d') : null;
$startLabel = $task->planned_start_at ? jdate($task->planned_start_at)->format('Y/m/d') : null;
@endphp

<div class="space-y-5">
    {{-- ===================== Header (Gate §12) ===================== --}}
    {{-- ps-12 clears the Phase 1 foundation's absolutely-positioned close
         button (top-start corner) so the title is never covered. --}}
    <header class="space-y-3 border-b border-line-subtle pb-4 ps-12">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0 space-y-1">
                <p class="font-mono text-xs text-ink-subtle" dir="ltr">#{{ $task->id }}</p>
                <h2 id="task-drawer-title" class="text-lg font-extrabold leading-snug text-ink">
                    {{ $task->title }}
                </h2>
            </div>
            {{-- Status badge: canonical Phase 3 presentation, read-only (§13) --}}
            <x-ui.badge :variant="$statusView['variant']" dot size="md">{{ $statusView['label'] }}</x-ui.badge>
        </div>

        {{-- needs_rework distinction — text + icon, never color alone (§25) --}}
        @if ($isRework)
            <p class="inline-flex items-center gap-1.5 rounded-ui bg-danger-50 px-2.5 py-1 text-xs font-bold text-danger-700 ring-1 ring-inset ring-danger-200">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                </svg>
                این وظیفه برای اصلاح بازگشته است
            </p>
        @endif

        {{-- Review Tier (§19): whose action the task is waiting on --}}
        @if ($tier !== null)
            <p class="inline-flex items-center gap-1.5 rounded-ui bg-warning-50 px-2.5 py-1 text-xs font-medium text-warning-800 ring-1 ring-inset ring-warning-200">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                {{ $tier }}
            </p>
        @endif

        {{-- Header metadata (§12): project / assignee / priority --}}
        <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-ink-muted">
            <span class="inline-flex items-center gap-1.5">
                <svg class="h-3.5 w-3.5 text-ink-subtle" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                </svg>
                {{ $task->project?->name ?? '-' }}
            </span>
            @if ($assignee)
                <span class="inline-flex items-center gap-1.5">
                    <x-ui.avatar :name="$assignee->name" size="xs" />
                    {{ $assignee->name }}
                </span>
            @endif
            <span class="inline-flex items-center gap-1.5">
                <svg class="h-3.5 w-3.5 text-ink-subtle" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5h14M5 12h14M5 19h14" />
                </svg>
                {{ $priorityView['label'] }}
            </span>
        </div>
    </header>

    {{-- ===================== Description (§14) ===================== --}}
    <section aria-labelledby="task-drawer-description-title">
        <h3 id="task-drawer-description-title" class="mb-2 text-xs font-bold uppercase tracking-wider text-ink-subtle">توضیحات</h3>
        @if (filled($task->description))
            <p class="text-sm leading-relaxed text-ink-muted">{{ $task->description }}</p>
        @else
            <p class="rounded-ui border border-dashed border-line-strong bg-surface-inset/60 px-3 py-3 text-xs text-ink-subtle">
                توضیحاتی برای این وظیفه ثبت نشده است.
            </p>
        @endif
    </section>

    {{-- ===================== Metadata (§15) ===================== --}}
    <section aria-labelledby="task-drawer-meta-title">
        <h3 id="task-drawer-meta-title" class="mb-2 text-xs font-bold uppercase tracking-wider text-ink-subtle">اطلاعات تکمیلی</h3>
        <div class="grid grid-cols-2 gap-2.5">
            <x-ui.key-val label="پروژه">{{ $task->project?->name ?? '-' }}</x-ui.key-val>
            <x-ui.key-val label="ماژول / مرحله">
                @if ($task->moduleStage)
                    {{ $task->moduleStage->name }}
                @else
                    <span class="font-normal text-ink-subtle">بدون مرحله</span>
                @endif
            </x-ui.key-val>
            <x-ui.key-val label="اولویت">
                <x-ui.badge :variant="$priorityView['variant']" size="sm">{{ $priorityView['label'] }}</x-ui.badge>
            </x-ui.key-val>
            <x-ui.key-val label="مسئول">
                @if ($assignee)
                    {{ $assignee->name }}
                @else
                    <span class="font-normal text-ink-subtle">تعیین نشده</span>
                @endif
            </x-ui.key-val>
            <x-ui.key-val label="پیمانکار">
                {{ $task->contractor?->name ?? '-' }}
            </x-ui.key-val>
            <x-ui.key-val label="وزن اجرایی">{{ $task->weight }}٪</x-ui.key-val>
            <x-ui.key-val label="تاریخ ایجاد" dir="ltr">{{ $createdLabel }}</x-ui.key-val>
            <x-ui.key-val label="شروع برنامه‌ای" dir="ltr">
                @if ($startLabel) {{ $startLabel }} @else <span class="font-normal text-ink-subtle">-</span> @endif
            </x-ui.key-val>
            <x-ui.key-val label="موعد انجام" dir="ltr">
                @if ($dueLabel) {{ $dueLabel }} @else <span class="font-normal text-ink-subtle">-</span> @endif
            </x-ui.key-val>
            <x-ui.key-val label="فاز WBS">
                @if ($task->wbsPhase)
                    {{ $task->wbsPhase->name }}
                @else
                    <span class="font-normal text-ink-subtle">تعیین نشده</span>
                @endif
            </x-ui.key-val>
        </div>
    </section>

    {{-- ===================== Dependencies (§16) ===================== --}}
    <section aria-labelledby="task-drawer-deps-title">
        <h3 id="task-drawer-deps-title" class="mb-2 text-xs font-bold uppercase tracking-wider text-ink-subtle">
            پیش‌نیازها
            <span class="mr-1 rounded-full bg-surface-inset px-1.5 py-0.5 text-[10px] font-bold text-ink-muted">
                {{ $task->dependenciesAsSuccessor->count() }}
            </span>
        </h3>
        @if ($task->dependenciesAsSuccessor->isNotEmpty())
            <ul class="space-y-1.5">
                @foreach ($task->dependenciesAsSuccessor as $dependency)
                    <li class="flex items-center justify-between gap-2 rounded-ui border border-line-subtle bg-surface-base px-3 py-2 text-xs">
                        <span class="min-w-0 truncate text-ink">
                            <span class="font-mono text-ink-subtle" dir="ltr">#{{ $dependency->predecessor_task_id }}</span>
                            {{ $dependency->predecessor?->title ?? '-' }}
                        </span>
                        @php $depStatus = BoardColumns::statusView((string) ($dependency->predecessor?->status ?? '')); @endphp
                        <x-ui.badge :variant="$depStatus['variant']" size="sm">{{ $depStatus['label'] }}</x-ui.badge>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="rounded-ui border border-dashed border-line-strong bg-surface-inset/60 px-3 py-3 text-xs text-ink-subtle">
                هیچ پیش‌نیازی برای این وظیفه تعریف نشده است.
            </p>
        @endif
    </section>

    {{-- ===================== Documents (§17) ===================== --}}
    <section aria-labelledby="task-drawer-docs-title">
        <h3 id="task-drawer-docs-title" class="mb-2 text-xs font-bold uppercase tracking-wider text-ink-subtle">
            مستندات
            <span class="mr-1 rounded-full bg-surface-inset px-1.5 py-0.5 text-[10px] font-bold text-ink-muted">
                {{ $task->documents->count() }}
            </span>
        </h3>
        @if ($task->documents->isNotEmpty())
            <ul class="space-y-1.5">
                @foreach ($task->documents as $document)
                    <li class="flex items-center justify-between gap-2 rounded-ui border border-line-subtle bg-surface-base px-3 py-2 text-xs">
                        <div class="flex min-w-0 items-center gap-2">
                            <svg class="h-4 w-4 shrink-0 text-ink-subtle" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                            </svg>
                            <div class="min-w-0">
                                <p class="truncate font-medium text-ink">{{ $document->original_name }}</p>
                                <p class="mt-0.5 text-[10px] text-ink-subtle" dir="ltr">
                                    {{ \App\Support\JalaliDate::toJalali($document->created_at) }} · {{ round($document->size / 1024, 1) }} KB
                                </p>
                            </div>
                        </div>
                        <x-ui.badge variant="muted" size="sm">{{ $document->mime_type }}</x-ui.badge>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="rounded-ui border border-dashed border-line-strong bg-surface-inset/60 px-3 py-3 text-xs text-ink-subtle">
                هیچ مستندی برای این وظیفه بارگذاری نشده است.
            </p>
        @endif
    </section>

    {{-- ===================== Approval history (§19) ===================== --}}
    <section aria-labelledby="task-drawer-approvals-title">
        <h3 id="task-drawer-approvals-title" class="mb-2 text-xs font-bold uppercase tracking-wider text-ink-subtle">تاریخچه بررسی</h3>
        @if ($task->approvals->isNotEmpty())
            <ul class="space-y-1.5">
                @foreach ($task->approvals->sortBy('sequence') as $approval)
                    @php
                        $approvalVariant = $approval->status === 'approved' ? 'success' : ($approval->status === 'needs_rework' ? 'danger' : 'warning');
                        $approvalLabel = $approval->status === 'approved' ? 'تأیید شد' : ($approval->status === 'needs_rework' ? 'نیاز به اصلاح' : 'رد شد');
                        $typeLabel = $approval->approval_type === 'final' ? 'تأیید نهایی' : 'تأیید فنی';
                    @endphp
                    <li class="rounded-ui border border-line-subtle bg-surface-base px-3 py-2 text-xs">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-medium text-ink">{{ $typeLabel }}</span>
                            <x-ui.badge :variant="$approvalVariant" size="sm">{{ $approvalLabel }}</x-ui.badge>
                        </div>
                        <p class="mt-1 text-[10px] text-ink-subtle" dir="ltr">
                            {{ $approval->actor?->name ?? '-' }} · {{ $approval->acted_at ? jdate($approval->acted_at)->format('Y/m/d') : '-' }}
                        </p>
                        @if (filled($approval->comment))
                            <p class="mt-1.5 text-[11px] leading-relaxed text-ink-muted">{{ $approval->comment }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        @else
            <p class="rounded-ui border border-dashed border-line-strong bg-surface-inset/60 px-3 py-3 text-xs text-ink-subtle">
                هنوز بررسی‌ای برای این وظیفه ثبت نشده است.
            </p>
        @endif
    </section>

    {{-- ===================== SLA (§20) ===================== --}}
    <section aria-labelledby="task-drawer-sla-title">
        <h3 id="task-drawer-sla-title" class="mb-2 text-xs font-bold uppercase tracking-wider text-ink-subtle">وضعیت SLA</h3>
        @if ($task->slaRecords->isNotEmpty())
            <div class="space-y-1.5">
                @foreach ($task->slaRecords as $sla)
                    <div class="flex items-center justify-between gap-2 rounded-ui border border-line-subtle bg-surface-base px-3 py-2 text-xs">
                        <span class="text-ink">
                            {{ $sla->sla_type === 'response' ? 'SLA پاسخ اولیه' : ($sla->sla_type === 'resolution' ? 'SLA تکمیل' : $sla->sla_type) }}
                        </span>
                        @if ($sla->is_breached)
                            <x-ui.badge variant="danger" size="sm" dot>نقض شده</x-ui.badge>
                        @elseif ($sla->stopped_at)
                            <x-ui.badge variant="success" size="sm" dot>متوقف شده</x-ui.badge>
                        @else
                            <x-ui.badge variant="info" size="sm" dot>در جریان</x-ui.badge>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            <p class="rounded-ui border border-dashed border-line-strong bg-surface-inset/60 px-3 py-3 text-xs text-ink-subtle">
                تایمر SLA برای این وظیفه فعال نشده است.
            </p>
        @endif
    </section>

    {{-- ===================== Activity log (§22) ===================== --}}
    @if ($task->activityLogs->isNotEmpty())
        <section aria-labelledby="task-drawer-activity-title">
            <h3 id="task-drawer-activity-title" class="mb-2 text-xs font-bold uppercase tracking-wider text-ink-subtle">آخرین فعالیت‌ها</h3>
            <ul class="space-y-2">
                @foreach ($task->activityLogs->sortByDesc('created_at')->take(5) as $log)
                    <li class="flex items-start gap-2.5 text-xs">
                        <span class="mt-1 h-1.5 w-1.5 shrink-0 rounded-full bg-ink-subtle" aria-hidden="true"></span>
                        <div>
                            <p class="text-ink">
                                <span class="font-mono text-ink-subtle" dir="ltr">{{ $log->action }}</span>
                                @if ($log->user) · {{ $log->user->name }} @endif
                            </p>
                            <p class="mt-0.5 text-[10px] text-ink-subtle" dir="ltr">{{ jdate($log->created_at)->format('Y/m/d H:i') }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- ===================== Full detail CTA (§24) ===================== --}}
    <div class="border-t border-line-subtle pt-4">
        <a href="{{ route('tasks.show', $task->id) }}"
           class="inline-flex w-full items-center justify-center gap-2 rounded-ui border border-line bg-surface-base px-4 py-2.5 text-sm font-bold text-ink transition hover:border-line-strong hover:bg-surface-inset focus:outline-none focus-visible:ring-2 focus-visible:ring-primary">
            مشاهده جزئیات کامل
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
        </a>
    </div>
</div>
