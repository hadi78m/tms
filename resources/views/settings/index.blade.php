@extends('layouts.app')

@section('title', 'تنظیمات سیستم')
@section('header_title', 'تنظیمات سیستم')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    @if(session('status'))
        <div class="bg-green-50 border border-green-200 text-green-800 px-5 py-4 rounded-xl flex items-start gap-3">
            <svg class="w-5 h-5 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 bg-slate-50">
            <h2 class="text-lg font-bold text-slate-800">پیکربندی سامانه</h2>
        </div>

        <form action="{{ route('settings.update') }}" method="POST" class="p-6 space-y-6">
            @csrf

            <div class="space-y-4">
                <!-- Approval Mode -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">حالت تایید وظایف</label>
                    @php $approvalMode = $settings->where('key', 'approval_mode')->first()?->parsed_value ?? 'two_tier'; @endphp
                    <select name="approval_mode" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 bg-white">
                        <option value="two_tier" {{ $approvalMode === 'two_tier' ? 'selected' : '' }}>دو مرحله‌ای (ناظر + بهره‌بردار)</option>
                        <option value="employer_only" {{ $approvalMode === 'employer_only' ? 'selected' : '' }}>تک مرحله‌ای (فقط بهره‌بردار)</option>
                    </select>
                </div>

                <!-- Allow Reopen -->
                <div class="flex items-center justify-between p-4 border border-slate-200 rounded-lg">
                    <div>
                        <h4 class="text-sm font-medium text-slate-800">امکان بازگشایی وظایف تایید شده</h4>
                        <p class="text-xs text-slate-500 mt-1">پس از تایید نهایی قابلیت بازگشت به وضعیت در حال انجام را دارد.</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="allow_reopen" value="true" class="sr-only peer" {{ ($settings->where('key', 'allow_reopen')->first()?->parsed_value ?? false) ? 'checked' : '' }}>
                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                    </label>
                </div>

                <!-- Require Evidence -->
                <div class="flex items-center justify-between p-4 border border-slate-200 rounded-lg">
                    <div>
                        <h4 class="text-sm font-medium text-slate-800">الزام آپلود مستندات در زمان اتمام</h4>
                        <p class="text-xs text-slate-500 mt-1">پیمانکار جهت ثبت پایان وظیفه باید حتما فایلی آپلود کند.</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="require_evidence_on_submit" value="true" class="sr-only peer" {{ ($settings->where('key', 'require_evidence_on_submit')->first()?->parsed_value ?? true) ? 'checked' : '' }}>
                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                    </label>
                </div>

                <!-- Lock Weight -->
                <div class="flex items-center justify-between p-4 border border-slate-200 rounded-lg">
                    <div>
                        <h4 class="text-sm font-medium text-slate-800">قفل شدن وزن</h4>
                        <p class="text-xs text-slate-500 mt-1">تغییر وزن فقط از طریق درخواست تغییر وزن مجاز باشد.</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="lock_weight" value="true" class="sr-only peer" {{ ($settings->where('key', 'lock_weight')->first()?->parsed_value ?? true) ? 'checked' : '' }}>
                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                    </label>
                </div>
            </div>

            <div class="flex justify-end pt-6">
                <button type="submit" class="bg-blue-600 text-white px-5 py-2 rounded-lg hover:bg-blue-700 font-medium transition flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    ذخیره تنظیمات
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
