@extends('layouts.app')

@section('title', 'گزارش‌های سیستم')
@section('header_title', 'گزارش‌های سیستم')

@section('content')
{{-- Phase 5B-4 — Visual Migration (گزارش‌های سیستم).
     فقط زبان بصری به Design System منتقل شده است: فرم GET و نام فیلدها
     (project_id/from/to)، selected-state، لینک‌های خروجی CSV، جدول، محاسبات
     و معناشناسی اعداد دست‌نخورده‌اند. سطوح عنوان صفحه (h2/h3) حفظ شده‌اند —
     h1 به دلیل وابستگی به app-header گزارش می‌شود (DEFER، Heading Architecture
     طبق Option B دست‌نخورده است). --}}
<div class="max-w-6xl mx-auto space-y-6">

    {{-- V1.10 — DEC-046 (5-1 Project Filter + 5-2 Period Filter) --}}
    <x-ui.panel>
        <x-slot:header>
            <header class="border-b border-line-subtle px-5 py-3.5">
                <h2 class="text-lg font-extrabold text-ink">فیلتر گزارش</h2>
                <p class="mt-1 text-xs text-ink-muted">فیلتر دوره بر تاریخ تصمیم تأیید (decided_at) اعمال می‌شود؛ وزن تخصیص‌یافته مستقل از دوره است.</p>
            </header>
        </x-slot:header>

        <form method="GET" action="{{ route('reports.index') }}" class="flex flex-wrap items-end gap-3">
            <div>
                <label for="report-project" class="mb-1 block text-xs font-bold text-ink-muted">پروژه</label>
                <select id="report-project" name="project_id" class="rounded-ui border-line bg-surface-base px-3 py-2 text-sm text-ink shadow-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/30">
                    <option value="">همهٔ پروژه‌ها</option>
                    @foreach($projects as $project)
                        <option value="{{ $project->id }}" {{ $filters['project_id'] === (int) $project->id ? 'selected' : '' }}>{{ $project->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="report-from" class="mb-1 block text-xs font-bold text-ink-muted">از تاریخ (میلادی)</label>
                <input id="report-from" type="date" name="from" value="{{ $filters['from'] }}" class="rounded-ui border-line bg-surface-base px-3 py-2 text-sm text-ink shadow-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/30" dir="ltr">
            </div>
            <div>
                <label for="report-to" class="mb-1 block text-xs font-bold text-ink-muted">تا تاریخ (میلادی)</label>
                <input id="report-to" type="date" name="to" value="{{ $filters['to'] }}" class="rounded-ui border-line bg-surface-base px-3 py-2 text-sm text-ink shadow-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/30" dir="ltr">
            </div>
            <button type="submit" class="inline-flex items-center rounded-ui bg-primary px-4 py-2 text-sm font-bold text-white shadow-ui-soft transition hover:bg-primary-hover focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                اعمال فیلتر
            </button>
            <a href="{{ route('reports.index') }}" class="inline-flex items-center rounded-ui border border-line bg-surface-base px-4 py-2 text-sm font-bold text-ink-muted transition hover:border-line-strong hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-primary">
                حذف فیلترها
            </a>
        </form>
    </x-ui.panel>

    {{-- KPI Cards — همان سه شاخص قبلی، همان number_format، فقط chrome عوض شده است. --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <x-ui.panel body-class="p-6 flex items-center gap-4">
            <span class="rounded-ui bg-primary/10 p-3 text-primary" aria-hidden="true">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            </span>
            <div>
                <p class="text-sm font-medium text-ink-muted">وزن کل تسک‌ها</p>
                <p class="text-2xl font-bold text-ink">{{ number_format($totalWeight, 1) }}</p>
            </div>
        </x-ui.panel>

        <x-ui.panel body-class="p-6 flex items-center gap-4">
            <span class="rounded-ui bg-success-100 p-3 text-success-700" aria-hidden="true">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </span>
            <div>
                <p class="text-sm font-medium text-ink-muted">مبلغ تأییدشدهٔ مراحل</p>
                <p class="text-2xl font-bold text-ink">{{ number_format($stageProgress['total_approved'], 2) }}</p>
                <p class="text-xs text-ink-subtle">از مجموع {{ number_format($stageProgress['total_allocated'], 2) }} وزن تخصیص‌یافتهٔ مراحل</p>
            </div>
        </x-ui.panel>

        <x-ui.panel body-class="p-6 flex items-center gap-4">
            <span class="rounded-ui bg-danger-100 p-3 text-danger-700" aria-hidden="true">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </span>
            <div>
                <p class="text-sm font-medium text-ink-muted">وظایف دارای تاخیر بحرانی</p>
                <p class="text-2xl font-bold text-danger-700">{{ $delayedTasks }}</p>
            </div>
        </x-ui.panel>
    </div>

    {{-- Stage progress table --}}
    <x-ui.panel body-class="p-0">
        <x-slot:header>
            <header class="border-b border-line-subtle px-5 py-3.5">
                <h2 class="text-lg font-extrabold text-ink">پیشرفت مراحل (مبتنی بر مبلغ تأییدشده)</h2>
                <p class="mt-1 text-xs text-ink-muted">
                    منبع این گزارش، مبالغ تأییدشدهٔ فعالِ مراحل است؛ مبالغ باطل‌شده و ردشده محاسبه نمی‌شوند.
                </p>
            </header>
        </x-slot:header>

        @if ($stageProgress['rows']->isEmpty())
            <div class="p-5">
                <x-ui.empty-state title="هنوز ساختار ماژول/مرحله‌ای برای گزارش وجود ندارد." />
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="border-b border-line bg-surface-inset">
                        <tr>
                            <th class="px-6 py-3 text-right font-bold text-ink-muted">پروژه</th>
                            <th class="px-6 py-3 text-right font-bold text-ink-muted">ماژول</th>
                            <th class="px-6 py-3 text-right font-bold text-ink-muted">مرحله</th>
                            <th class="px-6 py-3 text-right font-bold text-ink-muted">وزن تخصیص‌یافته</th>
                            <th class="px-6 py-3 text-right font-bold text-ink-muted">مبلغ تأییدشدهٔ مراحل</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line-subtle">
                        @foreach ($stageProgress['rows'] as $row)
                            <tr class="transition-colors hover:bg-surface-inset/60">
                                <td class="px-6 py-3 text-ink-muted">{{ $row['stage']->module->project->name ?? '-' }}</td>
                                <td class="px-6 py-3 text-ink-muted">{{ $row['stage']->module->name }}</td>
                                <td class="px-6 py-3">
                                    <a href="{{ route('modules.stages.show', $row['stage']->id) }}" class="font-bold text-primary hover:underline">
                                        {{ $row['stage']->name }}
                                    </a>
                                </td>
                                <td class="px-6 py-3 tabular-nums text-ink-muted">{{ number_format($row['allocated'], 2) }}٪</td>
                                <td class="px-6 py-3 tabular-nums font-medium {{ $row['approved'] > 0 ? 'text-success-700' : 'text-ink-subtle' }}">
                                    {{ number_format($row['approved'], 2) }}٪
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <x-slot:footer>
            @if ($stageProgress['excluded_no_stage_tasks'] > 0)
                <div class="border-t border-warning-200 bg-warning-50 px-6 py-3 text-xs text-warning-800">
                    {{ $stageProgress['excluded_no_stage_tasks'] }} وظیفه بدون مرحله (module_stage_id خالی) از این گزارش مرحله‌محور مستثنی است؛ این وظایف در سایر گزارش‌ها و فهرست وظایف به قوت خود باقی‌اند.
                </div>
            @endif
        </x-slot:footer>
    </x-ui.panel>

    {{-- Export Actions --}}
    <x-ui.panel>
        <x-slot:header>
            <header class="border-b border-line-subtle px-5 py-3.5">
                <h2 class="text-lg font-extrabold text-ink">برون‌ریزی داده‌ها (CSV Export)</h2>
            </header>
        </x-slot:header>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="rounded-ui-md border border-line-subtle bg-surface-inset/40 p-5 transition-colors hover:border-primary/40">
                <h3 class="mb-2 text-lg font-bold text-ink">گزارش وظایف تأیید نهایی‌شده</h3>
                <p class="mb-4 text-sm text-ink-muted">فهرست تمامی وظایف تایید نهایی شده (Approved) همراه با جزئیات پروژه و وزن تخصیص یافته.</p>
                <a href="{{ route('reports.export', ['type' => 'approved_tasks']) }}" class="inline-flex items-center gap-2 rounded-ui bg-surface-base px-4 py-2 text-sm font-bold text-ink-muted ring-1 ring-inset ring-line transition hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-primary">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    دانلود CSV
                </a>
            </div>

            <div class="rounded-ui-md border border-line-subtle bg-surface-inset/40 p-5 transition-colors hover:border-primary/40">
                <h3 class="mb-2 text-lg font-bold text-ink">گزارش وضعیت و تاخیرات SLA</h3>
                <p class="mb-4 text-sm text-ink-muted">گزارش جامع از تمامی وظایف سیستم به همراه وضعیت فعلی و بررسی تاخیر از موعد برنامه‌ریزی شده.</p>
                <a href="{{ route('reports.export', ['type' => 'all_tasks']) }}" class="inline-flex items-center gap-2 rounded-ui bg-surface-base px-4 py-2 text-sm font-bold text-ink-muted ring-1 ring-inset ring-line transition hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-primary">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    دانلود CSV
                </a>
            </div>

            {{-- V1.10 — DEC-046 (5-3 Stage CSV) --}}
            <div class="rounded-ui-md border border-line-subtle bg-surface-inset/40 p-5 transition-colors hover:border-primary/40 md:col-span-2">
                <h3 class="mb-2 text-lg font-bold text-ink">گزارش مرحله‌محور (CSV)</h3>
                <p class="mb-4 text-sm text-ink-muted">خروجی CSV از پیشرفت مراحل بر اساس «مبلغ تأییدشدهٔ مراحل» — با همان فیلترهای پروژه/دورهٔ اعمال‌شده در گزارش.</p>
                <a href="{{ route('reports.export', array_filter(['type' => 'stage_progress', 'project_id' => $filters['project_id'], 'from' => $filters['from'], 'to' => $filters['to']])) }}" class="inline-flex items-center gap-2 rounded-ui bg-surface-base px-4 py-2 text-sm font-bold text-ink-muted ring-1 ring-inset ring-line transition hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-primary">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    دانلود CSV مراحل
                </a>
            </div>
        </div>
    </x-ui.panel>
</div>
@endsection
