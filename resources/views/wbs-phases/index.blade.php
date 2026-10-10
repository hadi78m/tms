@extends('layouts.app')

@section('title', 'فازهای WBS - TMS')
@section('header_title', 'فازهای WBS')

@section('content')
{{-- Phase 5B-3 — Visual Migration (فازهای WBS).
     فقط زبان بصری منتقل شده است. فرم ایجاد فاز (route/فیلدها/required/
     maxlength)، لینک‌ها و وضعیت‌های چک‌لیست دست‌نخورده‌اند؛ نشان‌های
     وضعیت فقط به variantهای design-system ترجمه شده‌اند. --}}
<div class="mx-auto max-w-6xl space-y-6">
    <h1 class="text-2xl font-bold text-ink">فازهای WBS</h1>

    <x-ui.panel title="ایجاد فاز جدید" body-class="p-5 sm:p-6">
        <x-slot:subtitle>
            خروجی واقعی فاز، چک‌لیست آن است. فازهای جدید به‌صورت خودکار در وضعیت «در انتظار» ایجاد می‌شوند.
        </x-slot:subtitle>

        <form method="POST" action="{{ route('wbs-phases.store') }}" class="grid grid-cols-1 items-end gap-4 md:grid-cols-3">
            @csrf
            <div>
                <label for="wbs-project" class="mb-1 block text-sm font-bold text-ink">پروژه</label>
                <select id="wbs-project" name="project_id" class="w-full rounded-ui border-line-strong bg-surface-base px-3 py-2 text-sm text-ink focus:border-primary focus:ring-2 focus:ring-primary/30" required>
                    @foreach ($projects as $project)
                        <option value="{{ $project->id }}">{{ $project->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="wbs-name" class="mb-1 block text-sm font-bold text-ink">نام فاز</label>
                <input id="wbs-name" type="text" name="name" class="w-full rounded-ui border-line-strong px-3 py-2 text-sm text-ink focus:border-primary focus:ring-2 focus:ring-primary/30" required maxlength="255">
            </div>
            <div>
                <label for="wbs-weight" class="mb-1 block text-sm font-bold text-ink">وزن (Legacy)</label>
                <input id="wbs-weight" type="number" name="weight" step="0.01" min="0" max="100" value="0" class="w-full rounded-ui border-line-strong px-3 py-2 text-sm text-ink focus:border-primary focus:ring-2 focus:ring-primary/30" required>
            </div>
            <div>
                <label for="wbs-duration" class="mb-1 block text-sm font-bold text-ink">مدت برنامه‌ریزی‌شده</label>
                <input id="wbs-duration" type="number" name="planned_duration" min="1" class="w-full rounded-ui border-line-strong px-3 py-2 text-sm text-ink focus:border-primary focus:ring-2 focus:ring-primary/30" required>
            </div>
            <div>
                <label for="wbs-unit" class="mb-1 block text-sm font-bold text-ink">واحد مدت</label>
                <input id="wbs-unit" type="text" name="duration_unit" value="day" class="w-full rounded-ui border-line-strong px-3 py-2 text-sm text-ink focus:border-primary focus:ring-2 focus:ring-primary/30" required maxlength="50">
            </div>
            <div>
                <button type="submit" class="rounded-ui bg-primary px-5 py-2 font-bold text-white shadow-ui-soft transition hover:bg-primary-hover focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">ایجاد فاز</button>
            </div>
        </form>
    </x-ui.panel>

    <x-ui.panel title="فهرست فازها" body-class="p-0">
        @if ($phases->isEmpty())
            <div class="p-4">
                <x-ui.empty-state title="هیچ فازی ثبت نشده است." />
            </div>
        @else
            <ul class="divide-y divide-line-subtle">
                @foreach ($phases as $phase)
                    <li class="flex items-center justify-between gap-3 px-6 py-4">
                        <div>
                            <a href="{{ route('wbs-phases.show', $phase->id) }}" class="rounded-ui font-bold text-primary transition hover:text-primary-hover focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                                {{ $phase->name }}
                            </a>
                            <span class="ms-2 text-xs text-ink-subtle">{{ $phase->project->name ?? '-' }}</span>
                        </div>
                        <div class="flex items-center gap-3 text-xs">
                            <span class="text-ink-muted">{{ $phase->checklistItems->count() }} آیتم چک‌لیست</span>
                            @if ($phase->completion_status === 'pending')
                                <x-ui.badge variant="warning">در انتظار</x-ui.badge>
                            @elseif ($phase->completion_status === 'completed')
                                <x-ui.badge variant="success">تکمیل‌شده</x-ui.badge>
                            @else
                                <x-ui.badge variant="danger">تکمیل‌نشده</x-ui.badge>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.panel>
</div>
@endsection
