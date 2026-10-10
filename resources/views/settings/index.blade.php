@extends('layouts.app')

@section('title', 'تنظیمات سیستم')
@section('header_title', 'تنظیمات سیستم')

@section('content')
{{-- Phase 5B-3 — Visual Migration (تنظیمات سیستم).
     فقط زبان بصری منتقل شده است. action فرم، سه toggle با name/checked
     پیش‌فرض، متن توضیحات و دکمهٔ ذخیره دست‌نخورده‌اند. پیام‌های فلش
     یک‌بار در layouts/app.blade.php رندر می‌شوند — اینجا تکرار نمی‌شوند. --}}
<div class="mx-auto max-w-4xl space-y-6">
    <x-ui.panel title="پیکربندی سامانه" body-class="p-5 sm:p-6">
        <form action="{{ route('settings.update') }}" method="POST" class="space-y-6">
            @csrf

            <div class="space-y-4">
                <!-- Approval Mode -->
                <div>
                    <label for="settings-approval-mode" class="mb-1 block text-sm font-bold text-ink">حالت تایید وظایف</label>
                    @php $approvalMode = $settings->where('key', 'approval_mode')->first()?->parsed_value ?? 'two_tier'; @endphp
                    <select id="settings-approval-mode" name="approval_mode" class="w-full rounded-ui border-line-strong bg-surface-base px-4 py-2 text-sm text-ink focus:border-primary focus:ring-2 focus:ring-primary/30">
                        <option value="two_tier" {{ $approvalMode === 'two_tier' ? 'selected' : '' }}>دو مرحله‌ای (ناظر + بهره‌بردار)</option>
                        <option value="employer_only" {{ $approvalMode === 'employer_only' ? 'selected' : '' }}>تک مرحله‌ای (فقط بهره‌بردار)</option>
                    </select>
                </div>

                <!-- Allow Reopen -->
                <div class="flex items-center justify-between gap-4 rounded-ui border border-line-subtle p-4">
                    <div>
                        <h4 class="text-sm font-bold text-ink">امکان بازگشایی وظایف تایید شده</h4>
                        <p class="mt-1 text-xs text-ink-muted">پس از تایید نهایی قابلیت بازگشت به وضعیت در حال انجام را دارد.</p>
                    </div>
                    <label class="relative inline-flex cursor-pointer items-center">
                        <input type="checkbox" name="allow_reopen" value="true" class="sr-only peer" {{ ($settings->where('key', 'allow_reopen')->first()?->parsed_value ?? false) ? 'checked' : '' }}>
                        <div class="h-6 w-11 rounded-full bg-line-strong after:absolute after:top-[2px] after:end-[2px] after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow-sm after:transition-all peer-checked:bg-primary peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-primary/30 rtl:after:translate-x-full rtl:peer-checked:after:-translate-x-full"></div>
                    </label>
                </div>

                <!-- Require Evidence -->
                <div class="flex items-center justify-between gap-4 rounded-ui border border-line-subtle p-4">
                    <div>
                        <h4 class="text-sm font-bold text-ink">الزام آپلود مستندات در زمان اتمام</h4>
                        <p class="mt-1 text-xs text-ink-muted">پیمانکار جهت ثبت پایان وظیفه باید حتما فایلی آپلود کند.</p>
                    </div>
                    <label class="relative inline-flex cursor-pointer items-center">
                        <input type="checkbox" name="require_evidence_on_submit" value="true" class="sr-only peer" {{ ($settings->where('key', 'require_evidence_on_submit')->first()?->parsed_value ?? true) ? 'checked' : '' }}>
                        <div class="h-6 w-11 rounded-full bg-line-strong after:absolute after:top-[2px] after:end-[2px] after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow-sm after:transition-all peer-checked:bg-primary peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-primary/30 rtl:after:translate-x-full rtl:peer-checked:after:-translate-x-full"></div>
                    </label>
                </div>

                <!-- Lock Weight -->
                <div class="flex items-center justify-between gap-4 rounded-ui border border-line-subtle p-4">
                    <div>
                        <h4 class="text-sm font-bold text-ink">قفل شدن وزن</h4>
                        <p class="mt-1 text-xs text-ink-muted">تغییر وزن فقط از طریق درخواست تغییر وزن مجاز باشد.</p>
                    </div>
                    <label class="relative inline-flex cursor-pointer items-center">
                        <input type="checkbox" name="lock_weight" value="true" class="sr-only peer" {{ ($settings->where('key', 'lock_weight')->first()?->parsed_value ?? true) ? 'checked' : '' }}>
                        <div class="h-6 w-11 rounded-full bg-line-strong after:absolute after:top-[2px] after:end-[2px] after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow-sm after:transition-all peer-checked:bg-primary peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-primary/30 rtl:after:translate-x-full rtl:peer-checked:after:-translate-x-full"></div>
                    </label>
                </div>
            </div>

            <div class="pt-6 text-end">
                <button type="submit" class="inline-flex items-center gap-2 rounded-ui bg-primary px-5 py-2 font-bold text-white shadow-ui-soft transition hover:bg-primary-hover focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    ذخیره تنظیمات
                </button>
            </div>
        </form>
    </x-ui.panel>
</div>
@endsection
