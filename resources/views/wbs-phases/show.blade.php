@extends('layouts.app')

@section('title', 'فاز WBS: ' . $phase->name . ' - TMS')
@section('header_title', 'فاز WBS')

@section('content')
{{-- Phase 5B-7B — Visual Migration (نمای جزئیات فاز WBS).
     فقط زبان بصری منتقل شده است: اطلاعات فاز، چک‌لیست خروجی، فرم افزودن آیتم،
     و فرم تصمیم ناظر (route/decision values/comment/@csrf) عیناً حفظ شده‌اند.
     شرایط disable رادیویی مطابق قرارداد مخزن (HEAD) شرطی است:
     pending → complete/not_completed فعال؛ غیر pending → reopen فعال.
     هیچ محاسبه/تفسیر جدیدی از وضعیت یا وزن (DEC-052 معلق) اضافه نشده است. --}}
<div class="mx-auto max-w-5xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold text-ink">{{ $phase->name }}</h1>
        <a href="{{ route('wbs-phases.index') }}"
           class="rounded-ui text-sm font-bold text-primary transition hover:text-primary-hover focus:outline-none focus-visible:ring-2 focus-visible:ring-primary">
            بازگشت به فهرست
        </a>
    </div>

    <x-ui.panel body-class="p-5 sm:p-6">
        <x-slot:header>
            <header class="border-b border-line-subtle px-5 py-3.5 sm:px-6">
                <h2 class="text-[15px] font-extrabold leading-tight text-ink">اطلاعات فاز</h2>
            </header>
        </x-slot:header>

        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
            <x-ui.key-val label="پروژه" class="md:col-span-1">
                {{ $phase->project->name ?? '-' }}
            </x-ui.key-val>
            <div class="rounded-ui border border-line-subtle bg-surface-base px-3 py-2">
                <p class="text-[11px] font-semibold text-ink-subtle">وضعیت</p>
                <div class="mt-1">
                    <x-ui.badge :variant="$phase->completion_status === 'completed' ? 'success'
                        : ($phase->completion_status === 'not_completed' ? 'danger' : 'warning')" dot>
                        @if ($phase->completion_status === 'pending')
                            در انتظار
                        @elseif ($phase->completion_status === 'completed')
                            تکمیل‌شده
                        @else
                            تکمیل‌نشده (اعلام ناظر)
                        @endif
                    </x-ui.badge>
                </div>
            </div>
            <x-ui.key-val label="خروجی مورد انتظار" class="md:col-span-2">
                {{ $phase->expected_output }}
            </x-ui.key-val>
            @if ($phase->supervisor_comment)
                <div class="md:col-span-2">
                    <span class="text-sm text-ink-muted">یادداشت ناظر:</span>
                    <p class="mt-0.5 text-sm font-medium text-ink">{{ $phase->supervisor_comment }}</p>
                </div>
            @endif
        </div>
    </x-ui.panel>

    <!-- Checklist -->
    <x-ui.panel body-class="p-0">
        <x-slot:header>
            <div class="border-b border-line-subtle px-5 py-3.5 text-[15px] font-extrabold text-ink sm:px-6">
                چک‌لیست خروجی‌ها
            </div>
        </x-slot:header>

        @if ($phase->checklistItems->isEmpty())
            <div class="p-5 sm:p-6">
                <x-ui.empty-state title="آیتمی در چک‌لیست ثبت نشده است."
                                  description="با فرم پایین اولین خروجی مورد انتظار را اضافه کنید.">
                </x-ui.empty-state>
            </div>
        @else
            <ul class="divide-y divide-line-subtle">
                @foreach ($phase->checklistItems as $item)
                    <li class="px-5 sm:px-6">
                        <div class="flex flex-wrap items-start justify-between gap-3 py-4">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-sm font-bold text-ink">{{ $item->title }}</span>
                                    @if ($item->is_completed)
                                        <x-ui.badge variant="success" dot>تکمیل‌شده</x-ui.badge>
                                    @endif
                                </div>
                                @if ($item->description)
                                    <p class="mt-1 text-xs text-ink-muted">{{ $item->description }}</p>
                                @endif
                                @if ($item->is_completed && $item->completedByUser)
                                    <p class="mt-1 text-xs text-ink-subtle">توسط {{ $item->completedByUser->name }}</p>
                                @endif
                            </div>
                            <div class="flex gap-2">
                                @if ($item->is_completed)
                                    <form method="POST" action="{{ route('wbs-phases.checklist-items.reopen', [$phase->id, $item->id]) }}">
                                        @csrf
                                        <button type="submit"
                                                class="rounded-ui border border-line-strong bg-surface-base px-3 py-1.5 text-xs font-bold text-ink transition hover:bg-surface-inset focus:outline-none focus-visible:ring-2 focus-visible:ring-primary">
                                            بازگشایی
                                        </button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('wbs-phases.checklist-items.complete', [$phase->id, $item->id]) }}">
                                        @csrf
                                        <button type="submit"
                                                class="rounded-ui bg-success-600 px-3 py-1.5 text-xs font-bold text-white shadow-ui-soft transition hover:bg-success-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-success-500">
                                            تکمیل
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif

        <form method="POST" action="{{ route('wbs-phases.checklist-items.store', $phase->id) }}"
              class="border-t border-line-subtle p-5 sm:p-6">
            @csrf
            <div class="flex flex-wrap items-end gap-3">
                <div class="min-w-[220px] flex-1">
                    <label for="checklist-title" class="mb-1 block text-sm font-bold text-ink">آیتم جدید</label>
                    <input id="checklist-title" type="text" name="title" maxlength="255"
                           class="w-full rounded-ui border border-line-strong bg-surface-base px-3 py-2 text-sm text-ink outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/30"
                           required>
                </div>
                <div class="min-w-[220px] flex-1">
                    <label for="checklist-description" class="mb-1 block text-sm font-bold text-ink">توضیح (اختیاری)</label>
                    <input id="checklist-description" type="text" name="description" maxlength="2000"
                           class="w-full rounded-ui border border-line-strong bg-surface-base px-3 py-2 text-sm text-ink outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/30">
                </div>
                <button type="submit"
                        class="rounded-ui bg-primary px-4 py-2 text-sm font-bold text-white shadow-ui-soft transition hover:bg-primary-hover focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                    افزودن
                </button>
            </div>
        </form>
    </x-ui.panel>

    {{-- Part 5B-8 — UI authorization: decision UI فقط برای دارندگان توانایی
         decide (نقش supervisor + عضویت فعال پروژه — WbsPhasePolicy) رندر می‌شود.
         Admin/دیگر نقش‌های خواندنی فرم تصمیم را به‌عنوان اقدام موجود نمی‌بینند.
         این صرفاً پنهان‌سازی ارائه‌ی UI است: مرز واقعی همچنان سمت سرور
         (middleware + policy روی POST) است و هیچ قانون مجوز جدیدی اضافه نشده. --}}
    @can('decide', $phase)
    <!-- Supervisor decision -->
    <x-ui.panel body-class="p-5 sm:p-6">
        <x-slot:header>
            <header class="border-b border-line-subtle px-5 py-3.5 sm:px-6">
                <h2 class="text-[15px] font-extrabold leading-tight text-ink">تصمیم ناظر پروژه</h2>
            </header>
        </x-slot:header>

        <form method="POST" action="{{ route('wbs-phases.decide', $phase->id) }}" class="space-y-4">
            @csrf
            <fieldset class="space-y-2">
                <legend class="mb-1 text-sm font-bold text-ink">تصمیم</legend>
                <label class="flex items-center gap-2 text-sm font-medium text-ink">
                    <input type="radio" name="decision" value="complete"
                           {{ $phase->completion_status !== 'pending' ? 'disabled' : '' }} required>
                    اعلام تکمیل فاز
                </label>
                <label class="flex items-center gap-2 text-sm font-medium text-ink">
                    <input type="radio" name="decision" value="not_completed"
                           {{ $phase->completion_status !== 'pending' ? 'disabled' : '' }}>
                    اعلام تکمیل‌نشده (با دلیل)
                </label>
                <label class="flex items-center gap-2 text-sm font-medium text-ink">
                    <input type="radio" name="decision" value="reopen"
                           {{ $phase->completion_status === 'pending' ? 'disabled' : '' }}>
                    بازگشایی فاز
                </label>
            </fieldset>
            @if ($phase->completion_status !== 'pending')
                <p class="text-xs text-ink-muted">این تصمیم قبلی ناظر ثبت‌شده است و می‌تواند دوباره بازگشایی شود.</p>
            @endif

            <div>
                <label for="decision-comment" class="mb-1 block text-sm font-bold text-ink">یادداشت / دلیل (برای تکمیل‌نشده الزامی)</label>
                <input id="decision-comment" type="text" name="comment" maxlength="2000"
                       class="w-full rounded-ui border border-line-strong bg-surface-base px-3 py-2 text-sm text-ink outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/30">
            </div>
            <button type="submit"
                    class="rounded-ui border border-line-strong bg-surface-base px-5 py-2 text-sm font-bold text-ink transition hover:bg-surface-inset focus:outline-none focus-visible:ring-2 focus-visible:ring-primary">
                ثبت تصمیم
            </button>
        </form>
    </x-ui.panel>
    @endcan
</div>
@endsection
