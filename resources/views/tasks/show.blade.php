@extends('layouts.app')

@use('App\Domain\Enums\TaskPriority')
@use('App\Support\TaskBoard\BoardColumns')

@section('title', 'جزئیات وظیفه: ' . $task->title)
@section('header_title', 'جزئیات وظیفه')

@section('content')
{{-- Phase 5B-2 — Visual Migration (جزئیات وظیفه).
     فقط زبان بصری (Panel/Badge/Key-Value/توکن‌ها) منتقل شده است.
     تمام فرم‌ها، route‌ها، name/id فیلدها، دروازه‌های مجوز (@can/hasRole)،
     شرط‌های وضعیت، متن‌های فارسی و محاسبات نمایشی دست‌نخورده‌اند. --}}
<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <!-- ستون اصلی اطلاعات -->
    <div class="space-y-6 lg:col-span-2">
        {{-- پیام‌های فلش یک‌بار و به‌صورت یکپارچه در layouts/app.blade.php رندر می‌شوند.
             تکرار آن‌ها اینجا باعث نمایش دوبارهٔ همان پیام می‌شد. --}}

        <!-- کارت مشخصات اصلی -->
        <x-ui.panel body-class="p-5 sm:p-6">
            <x-slot:header>
                <header class="flex flex-wrap items-center justify-between gap-3 border-b border-line-subtle px-5 py-3.5">
                    <h2 class="text-lg font-extrabold text-ink">مشخصات وظیفه #{{ $task->id }}</h2>
                    {{-- وضعیت — فقط از منبع واحد ارائهٔ وضعیت (BoardColumns) خوانده می‌شود؛
                         هیچ نگاشت تکراری‌ای در این ویو نگه داشته نمی‌شود. --}}
                    @php $statusView = BoardColumns::statusView((string) $task->status); @endphp
                    <x-ui.badge :variant="$statusView['variant']" dot size="md">{{ $statusView['label'] }}</x-ui.badge>
                </header>
            </x-slot:header>

            <h3 class="text-xl font-extrabold text-ink sm:text-2xl">{{ $task->title }}</h3>
            <p class="mt-2 mb-5 text-sm leading-relaxed text-ink-muted">{{ $task->description ?? 'توضیحاتی برای این وظیفه ثبت نشده است.' }}</p>

            @php $priorityView = TaskPriority::viewOf($task->priority); @endphp
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3">
                <x-ui.key-val label="پروژه مرتبط">{{ $task->project->name ?? '-' }}</x-ui.key-val>
                <x-ui.key-val label="پیمانکار مجری">{{ $task->contractor->name ?? '-' }}</x-ui.key-val>
                <x-ui.key-val label="مرحلهٔ ماژول (V1.10)">{{ $task->moduleStage?->name ?? 'بدون مرحله' }}</x-ui.key-val>
                <x-ui.key-val label="وزن اجرایی">{{ $task->weight }}٪</x-ui.key-val>
                <x-ui.key-val label="اولویت">
                    <x-ui.badge :variant="$priorityView['variant']">{{ $priorityView['label'] }}</x-ui.badge>
                </x-ui.key-val>
                <x-ui.key-val label="تاریخ شروع (برنامه‌ای)">
                    <span dir="ltr">{{ $task->planned_start_at ? jdate($task->planned_start_at)->format('Y/m/d') : '-' }}</span>
                </x-ui.key-val>
                <x-ui.key-val label="مهلت انجام (برنامه‌ای)">
                    <span dir="ltr">{{ $task->planned_due_at ? jdate($task->planned_due_at)->format('Y/m/d') : '-' }}</span>
                </x-ui.key-val>
            </div>
        </x-ui.panel>

        <!-- پیوست‌ها -->
        <x-ui.panel title="مستندات و پیوست‌ها" body-class="p-5 sm:p-6">
            <x-slot:actions>
                <form action="{{ route('documents.store', $task->id) }}" method="POST" enctype="multipart/form-data" class="flex flex-wrap items-center gap-2">
                    @csrf
                    <input type="text" name="claimed_at" placeholder="تاریخ شمسی: ۱۴۰۵/۰۱/۱۵" autocomplete="off" class="datedown rounded-ui border-line-strong px-2 py-1 text-xs text-left focus:border-primary focus:ring-2 focus:ring-primary/30" dir="ltr" title="زمان ادعایی انجام کار (شمسی)">
                    <input type="file" name="file" required class="w-48 text-xs text-ink-muted file:me-2 file:rounded-ui file:border-0 file:bg-primary/10 file:px-2 file:py-1 file:text-xs file:font-bold file:text-primary hover:file:bg-primary/20">
                    <button type="submit" class="rounded-ui bg-primary/10 px-3 py-1 text-sm font-bold text-primary transition hover:bg-primary/20 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary">
                        + بارگذاری سند
                    </button>
                </form>
            </x-slot:actions>

            @if($task->documents && $task->documents->count() > 0)
                <ul class="divide-y divide-line-subtle">
                    @foreach($task->documents as $doc)
                    <li class="flex items-center justify-between gap-3 py-3">
                        <div class="flex items-center gap-3">
                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-ui bg-surface-inset text-ink-subtle" aria-hidden="true">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                                </svg>
                            </span>
                            <div>
                                <p class="font-bold text-ink">{{ $doc->original_name ?? $doc->file_name }}</p>
                                <p class="mt-1 text-xs text-ink-muted" dir="ltr">
                                    Sys: {{ jdate($doc->recorded_at ?? $doc->created_at)->format('Y/m/d H:i') }} | {{ round($doc->size / 1024, 2) }} KB
                                    @if($doc->claimed_at)
                                    <span class="mx-1">|</span> <span class="font-bold text-warning-600" title="Claimed Time">Claim: {{ jdate($doc->claimed_at)->format('Y/m/d H:i') }}</span>
                                    @endif
                                </p>
                                @if($doc->file_hash)
                                <p class="mt-1 flex items-center gap-1 font-mono text-[10px] text-ink-subtle" dir="ltr" title="SHA-256 Hash">
                                    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364 0-1.457.39-2.823 1.07-4"/></svg>
                                    {{ substr($doc->file_hash, 0, 16) }}...
                                </p>
                                @endif
                            </div>
                        </div>
                        {{-- دانلود سند در این فاز پیاده‌سازی نمی‌شود؛ لینک شکستهٔ
                             (لنگر بدون مقصد) حذف شد تا تعامل جعلی نداشته باشیم. --}}
                    </li>
                    @endforeach
                </ul>
            @else
                <x-ui.empty-state title="مدرکی برای این وظیفه بارگذاری نشده است." />
            @endif
        </x-ui.panel>

        <!-- زیرتسک‌ها -->
        <x-ui.panel title="زیرتسک‌ها (Subtasks)" body-class="p-5 sm:p-6">
            <x-slot:actions>
                @if(auth()->user()->hasRole(['project_manager', 'admin']))
                <a href="{{ route('tasks.create', ['parent_id' => $task->id]) }}" class="rounded-ui bg-primary/10 px-3 py-1 text-sm font-bold text-primary transition hover:bg-primary/20 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary">
                    + افزودن زیرتسک
                </a>
                @endif
            </x-slot:actions>

            @if($task->childTasks && $task->childTasks->count() > 0)
                <ul class="divide-y divide-line-subtle">
                    @foreach($task->childTasks as $child)
                    <li class="flex items-center justify-between gap-3 py-3">
                        <div>
                            <a href="{{ route('tasks.show', $child->id) }}" class="rounded-ui font-bold text-ink transition-colors hover:text-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary">#{{ $child->id }} - {{ $child->title }}</a>
                            <p class="mt-1 text-xs text-ink-muted">وضعیت: {{ BoardColumns::statusLabel((string) $child->status) }}</p>
                        </div>
                    </li>
                    @endforeach
                </ul>
            @else
                <x-ui.empty-state title="این وظیفه هیچ زیرتسکی ندارد." />
            @endif
        </x-ui.panel>
    </div>

    <!-- سایدبار اطلاعات تکمیلی و عملیات -->
    <div class="space-y-6">
        {{-- ─── تغییر مرحله (V1.10 · DEC-042/043) ─── فقط ناظر، تا قبل از تأیید نهایی ─── --}}
        @if(auth()->user()->hasRole('supervisor'))
            @php
                $stageLocked = in_array($task->status, ['approved', 'cancelled']);
                $projectStages = $task->project_id !== null
                    ? \App\Models\ModuleStage::query()
                        ->join('modules as m', 'm.id', '=', 'module_stages.module_id')
                        ->where('m.project_id', $task->project_id)
                        ->orderBy('m.sort_order')->orderBy('module_stages.sort_order')
                        ->select('module_stages.*')->get()
                    : collect();
            @endphp
            <x-ui.panel title="مرحلهٔ تسک (Task ↔ Stage)" body-class="p-5">
                @if($stageLocked)
                    <p class="rounded-ui border border-line-subtle bg-surface-inset px-3 py-2 text-sm text-ink-muted">
                        🔒 پس از تأیید نهایی / لغو، مرحلهٔ تسک قفل است (DEC-043).
                    </p>
                @else
                    <form action="{{ route('tasks.stage', $task->id) }}" method="POST" class="space-y-2">
                        @csrf
                        <select name="module_stage_id" class="w-full rounded-ui border border-line-strong bg-surface-base px-3 py-2 text-sm text-ink focus:border-primary focus:ring-2 focus:ring-primary/30">
                            <option value="">بدون مرحله</option>
                            @foreach($projectStages as $stage)
                                <option value="{{ $stage->id }}" {{ $task->module_stage_id === $stage->id ? 'selected' : '' }}>
                                    {{ $stage->name }} ({{ $stage->module->name }})
                                </option>
                            @endforeach
                        </select>
                        <button type="submit" class="w-full rounded-ui bg-primary px-4 py-2 text-sm font-bold text-white shadow-ui-soft transition hover:bg-primary-hover focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                            ثبت مرحله
                        </button>
                    </form>
                @endif
            </x-ui.panel>
        @endif

        <!-- پیش‌نیازها (Dependencies) -->
        <x-ui.panel body-class="p-5">
            <x-slot:header>
                @php
                    $isBlocked = $task->dependenciesAsSuccessor()->whereHas('predecessor', function($q) {
                        $q->where('status', '!=', 'approved');
                    })->exists();
                @endphp
                <header class="flex flex-wrap items-center justify-between gap-3 border-b border-line-subtle px-5 py-3.5">
                    <h3 class="flex items-center gap-2 text-[15px] font-extrabold leading-tight text-ink">
                        پیش‌نیازها
                        @if($isBlocked)
                            <x-ui.badge variant="danger" dot title="قفل شده (پیش‌نیازها تکمیل نشده‌اند)">مسدود</x-ui.badge>
                        @endif
                    </h3>
                </header>
            </x-slot:header>

            @if($task->dependenciesAsSuccessor && $task->dependenciesAsSuccessor->count() > 0)
                <ul class="mb-4 space-y-3">
                    @foreach($task->dependenciesAsSuccessor as $dep)
                        @php $depApproved = $dep->predecessor->status === 'approved'; @endphp
                        <li class="flex items-center justify-between gap-2 rounded-ui border border-line-subtle bg-surface-inset p-2.5">
                            <a href="{{ route('tasks.show', $dep->predecessor_task_id) }}" class="rounded-ui text-sm font-bold text-ink transition-colors hover:text-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary">
                                #{{ $dep->predecessor_task_id }} - {{ Str::limit($dep->predecessor->title ?? '', 20) }}
                            </a>
                            <div class="flex items-center gap-2">
                                <x-ui.badge :variant="$depApproved ? 'success' : 'warning'">{{ $depApproved ? 'تایید شده' : 'ناتمام' }}</x-ui.badge>
                                @if(auth()->user()->hasRole(['project_manager', 'admin']))
                                <form action="{{ route('tasks.dependencies.destroy', [$task->id, $dep->id]) }}" method="POST" onsubmit="return confirm('آیا از حذف این پیش‌نیاز مطمئن هستید؟');">
                                    @csrf
                                    <button type="submit" aria-label="حذف پیش‌نیاز" class="grid h-7 w-7 place-items-center rounded-ui text-danger-500 transition hover:bg-danger-50 hover:text-danger-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-danger-500">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </form>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="mb-4 text-sm text-ink-muted">هیچ پیش‌نیازی تعریف نشده است.</p>
            @endif

            @if(auth()->user()->hasRole(['project_manager', 'admin']))
            <form action="{{ route('tasks.dependencies.store', $task->id) }}" method="POST" class="flex gap-2">
                @csrf
                <input type="number" name="depends_on_task_id" placeholder="شناسه وظیفه (ID)" required class="w-full rounded-ui border-line-strong px-3 py-1.5 text-sm text-ink focus:border-primary focus:ring-2 focus:ring-primary/30">
                <button type="submit" class="rounded-ui bg-primary px-3 py-1.5 text-sm font-bold text-white shadow-ui-soft transition hover:bg-primary-hover focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">افزودن</button>
            </form>
            @endif
        </x-ui.panel>

        <!-- تایمر و SLA -->
        <x-ui.panel title="وضعیت SLA" body-class="space-y-4 p-5">
            @if($task->slaRecords && $task->slaRecords->isNotEmpty())
                <div class="space-y-3">
                    @foreach($task->slaRecords as $sla)
                        <div class="rounded-ui border p-3 {{ $sla->is_breached ? 'border-danger-200 bg-danger-50' : ($sla->stopped_at ? 'border-line-subtle bg-surface-inset' : 'border-info-200 bg-info-50') }}">
                            <div class="mb-2 flex items-center justify-between gap-2">
                                <span class="text-xs font-extrabold text-ink">
                                    {{ $sla->sla_type === 'response' ? 'SLA پاسخ اولیه' : ($sla->sla_type === 'resolution' ? 'SLA تکمیل و حل' : $sla->sla_type) }}
                                </span>
                                <x-ui.badge :variant="$sla->is_breached ? 'danger' : ($sla->stopped_at ? 'success' : 'info')">
                                    {{ $sla->is_breached ? 'نقض شده' : ($sla->stopped_at ? 'متوقف شده' : 'در حال محاسبه') }}
                                </x-ui.badge>
                            </div>
                            <div class="space-y-1 text-xs text-ink-muted">
                                <div class="flex justify-between gap-2">
                                    <span>شروع:</span>
                                    <span dir="ltr">{{ $sla->started_at ? jdate($sla->started_at)->format('Y/m/d H:i') : '-' }}</span>
                                </div>
                                @if($sla->stopped_at)
                                    <div class="flex justify-between gap-2">
                                        <span>پایان:</span>
                                        <span dir="ltr">{{ $sla->stopped_at ? jdate($sla->stopped_at)->format('Y/m/d H:i') : '-' }}</span>
                                    </div>
                                    <div class="flex justify-between gap-2">
                                        <span>مدت زمان مصرفی:</span>
                                        <span dir="ltr">{{ $sla->actual_duration }} دقیقه</span>
                                    </div>
                                @else
                                    <div class="flex justify-between gap-2">
                                        <span>زمان سپری‌شده:</span>
                                        <span dir="ltr">{{ \Carbon\Carbon::parse($sla->started_at)->diffForHumans(null, true) }}</span>
                                    </div>
                                @endif
                                <div class="flex justify-between gap-2">
                                    <span>مهلت مجاز:</span>
                                    <span dir="ltr">{{ $sla->target_duration }} دقیقه</span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <x-ui.empty-state title="تایمر SLA هنوز فعال نشده است." />
            @endif

            @php
                $resolutionSla = $task->slaRecords ? $task->slaRecords->firstWhere('sla_type', 'resolution') : null;
            @endphp

            @if($task->status === 'assigned' && auth()->user()->contractor_id === $task->contractor_id)
                <form action="{{ route('tasks.start', $task->id) }}" method="POST" class="mb-3">
                    @csrf
                    <button type="submit" class="w-full rounded-ui bg-primary px-4 py-2 font-bold text-white shadow-ui-soft transition hover:bg-primary-hover focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 {{ $isBlocked ?? false ? 'opacity-50 cursor-not-allowed' : '' }}" {{ $isBlocked ?? false ? 'disabled' : '' }}>
                        @if($isBlocked ?? false)
                            🔒 امکان شروع نیست (پیش‌نیاز دارد)
                        @else
                            شروع وظیفه
                        @endif
                    </button>
                </form>
            @endif

            @if($task->status === 'in_progress' && auth()->user()->contractor_id === $task->contractor_id && (!$resolutionSla || !$resolutionSla->stopped_at))
                <form action="{{ route('tasks.submit', $task->id) }}" method="POST">
                    @csrf
                    <button type="submit" class="w-full rounded-ui bg-success-600 px-4 py-2 font-bold text-white shadow-ui-soft transition hover:bg-success-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-success-500 focus-visible:ring-offset-2">
                        اعلام اتمام وظیفه (ارسال جهت بررسی)
                    </button>
                </form>
            @endif
        </x-ui.panel>

        {{-- ─── کارتابل تایید فنی ناظر ─── --}}
        {{-- نمایش فقط برای دارندگان مجوز technical_approval و فقط در وضعیت under_review --}}
        @can('technical_approval')
            @if($task->status === 'under_review')
            <x-ui.panel title="تایید فنی ناظر" accent="#4F46E5" body-class="space-y-4 p-5">
                <x-slot:icon>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </x-slot:icon>

                {{-- دکمه تایید فنی --}}
                <form action="{{ route('approvals.store', $task->id) }}" method="POST" id="form-technical-approve">
                    @csrf
                    <input type="hidden" name="approval_type" value="technical">
                    <input type="hidden" name="status" value="approved">
                    <button type="submit"
                            id="btn-technical-approve"
                            class="flex w-full items-center justify-center gap-2 rounded-ui bg-primary px-4 py-2.5 font-bold text-white shadow-ui-soft transition hover:bg-primary-hover focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        تایید فنی (ناظر)
                    </button>
                </form>

                {{-- فرم نیاز به اصلاح توسط ناظر --}}
                <details class="group">
                    <summary class="flex cursor-pointer list-none items-center gap-1 text-sm font-bold text-danger-600 hover:text-danger-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-danger-500">
                        <svg class="h-4 w-4 transition group-open:rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                        اعلام نیاز به اصلاح
                    </summary>
                    <form action="{{ route('approvals.store', $task->id) }}" method="POST" class="mt-3 space-y-3" id="form-technical-rework">
                        @csrf
                        <input type="hidden" name="approval_type" value="technical">
                        <input type="hidden" name="status" value="needs_rework">
                        <div>
                            <label for="technical-rework-comment" class="mb-1 block text-sm font-bold text-ink">دلیل نیاز به اصلاح <span class="text-danger-500">*</span></label>
                            <textarea name="comment"
                                      id="technical-rework-comment"
                                      rows="3"
                                      required
                                      class="w-full rounded-ui border-line-strong px-3 py-2 text-sm text-ink focus:border-danger-500 focus:ring-2 focus:ring-danger-500/30"
                                      placeholder="دلیل بازگشت برای اصلاح را بنویسید..."></textarea>
                        </div>
                        <button type="submit"
                                id="btn-technical-rework"
                                class="w-full rounded-ui bg-danger-600 px-4 py-2 text-sm font-bold text-white shadow-ui-soft transition hover:bg-danger-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-danger-500 focus-visible:ring-offset-2">
                            بازگشت برای اصلاح
                        </button>
                    </form>
                </details>
            </x-ui.panel>
            @endif
        @endcan

        {{-- ─── کارتابل تایید نهایی بهره‌بردار ─── --}}
        {{-- نمایش فقط برای دارندگان مجوز final_approval و فقط در وضعیت supervisor_approved --}}
        @can('final_approval')
            @if($task->status === 'supervisor_approved')
            <x-ui.panel title="تایید نهایی بهره‌بردار" accent="#16a34a" body-class="space-y-4 p-5">
                <x-slot:icon>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                    </svg>
                </x-slot:icon>

                <div class="rounded-ui border border-success-100 bg-success-50 px-3 py-2 text-xs text-success-700">
                    ✓ تایید فنی ناظر در {{ $task->supervisor_approved_at ? jdate($task->supervisor_approved_at)->format('Y/m/d H:i') : '-' }} انجام شده است.
                </div>

                {{-- دکمه تایید نهایی --}}
                <form action="{{ route('approvals.store', $task->id) }}" method="POST" id="form-final-approve">
                    @csrf
                    <input type="hidden" name="approval_type" value="final">
                    <input type="hidden" name="status" value="approved">
                    <button type="submit"
                            id="btn-final-approve"
                            class="flex w-full items-center justify-center gap-2 rounded-ui bg-success-600 px-4 py-2.5 font-bold text-white shadow-ui-soft transition hover:bg-success-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-success-500 focus-visible:ring-offset-2">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        تایید نهایی (بهره‌بردار)
                    </button>
                </form>

                {{-- فرم نیاز به اصلاح توسط بهره‌بردار --}}
                <details class="group">
                    <summary class="flex cursor-pointer list-none items-center gap-1 text-sm font-bold text-danger-600 hover:text-danger-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-danger-500">
                        <svg class="h-4 w-4 transition group-open:rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                        رد و اعلام نیاز به اصلاح
                    </summary>
                    <form action="{{ route('approvals.store', $task->id) }}" method="POST" class="mt-3 space-y-3" id="form-final-rework">
                        @csrf
                        <input type="hidden" name="approval_type" value="final">
                        <input type="hidden" name="status" value="needs_rework">
                        <div>
                            <label for="final-rework-comment" class="mb-1 block text-sm font-bold text-ink">دلیل رد و اصلاح <span class="text-danger-500">*</span></label>
                            <textarea name="comment"
                                      id="final-rework-comment"
                                      rows="3"
                                      required
                                      class="w-full rounded-ui border-line-strong px-3 py-2 text-sm text-ink focus:border-danger-500 focus:ring-2 focus:ring-danger-500/30"
                                      placeholder="دلیل رد یا نیاز به اصلاح را بنویسید..."></textarea>
                        </div>
                        <button type="submit"
                                id="btn-final-rework"
                                class="w-full rounded-ui bg-danger-600 px-4 py-2 text-sm font-bold text-white shadow-ui-soft transition hover:bg-danger-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-danger-500 focus-visible:ring-offset-2">
                            رد و بازگشت برای اصلاح
                        </button>
                    </form>
                </details>
            </x-ui.panel>
            @endif
        @endcan
    </div>
</div>
@endsection
