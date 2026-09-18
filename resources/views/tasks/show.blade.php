@extends('layouts.app')

@section('title', 'جزئیات وظیفه: ' . $task->title)

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- ستون اصلی اطلاعات -->
    <div class="lg:col-span-2 space-y-6">
        <!-- کارت مشخصات اصلی -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="border-b border-slate-200 px-6 py-4 flex items-center justify-between">
                <h2 class="text-xl font-bold text-slate-800">مشخصات وظیفه #{{ $task->id }}</h2>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium 
                    @if($task->status === 'active') bg-blue-100 text-blue-800
                    @elseif($task->status === 'completed') bg-green-100 text-green-800
                    @else bg-slate-100 text-slate-800 @endif">
                    {{ __('status.' . $task->status) ?? $task->status }}
                </span>
            </div>
            <div class="p-6">
                <h3 class="text-2xl font-bold text-slate-900 mb-4">{{ $task->title }}</h3>
                <p class="text-slate-600 mb-6 leading-relaxed">{{ $task->description ?? 'توضیحاتی برای این وظیفه ثبت نشده است.' }}</p>
                
                <div class="grid grid-cols-2 md:grid-cols-3 gap-6">
                    <div>
                        <p class="text-sm text-slate-500 mb-1">پروژه مرتبط</p>
                        <p class="font-medium text-slate-800">{{ $task->project->name ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-slate-500 mb-1">پیمانکار مجری</p>
                        <p class="font-medium text-slate-800">{{ $task->contractor->name ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-slate-500 mb-1">وزن اجرایی</p>
                        <p class="font-medium text-slate-800">{{ $task->weight }}٪</p>
                    </div>
                    <div>
                        <p class="text-sm text-slate-500 mb-1">اولویت</p>
                        <p class="font-medium text-slate-800">{{ __('priority.' . $task->priority) ?? $task->priority }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-slate-500 mb-1">تاریخ شروع (برنامه‌ای)</p>
                        <p class="font-medium text-slate-800" dir="ltr">{{ $task->planned_start_at ? jdate($task->planned_start_at)->format('Y/m/d') : '-' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-slate-500 mb-1">مهلت انجام (برنامه‌ای)</p>
                        <p class="font-medium text-slate-800" dir="ltr">{{ $task->planned_due_at ? jdate($task->planned_due_at)->format('Y/m/d') : '-' }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- پیوست‌ها -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="border-b border-slate-200 px-6 py-4 flex justify-between items-center">
                <h3 class="text-lg font-bold text-slate-800">مستندات و پیوست‌ها</h3>
                <button class="text-sm text-blue-600 font-medium hover:text-blue-800">
                    + بارگذاری سند جدید
                </button>
            </div>
            <div class="p-6">
                @if($task->documents && $task->documents->count() > 0)
                    <ul class="divide-y divide-slate-100">
                        @foreach($task->documents as $doc)
                        <li class="py-3 flex justify-between items-center">
                            <div class="flex items-center gap-3">
                                <svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                                </svg>
                                <div>
                                    <p class="font-medium text-slate-800">{{ $doc->file_name }}</p>
                                    <p class="text-xs text-slate-500" dir="ltr">{{ jdate($doc->created_at)->format('Y/m/d H:i') }} | {{ round($doc->file_size / 1024, 2) }} KB</p>
                                </div>
                            </div>
                            <a href="#" class="text-blue-600 hover:text-blue-800 text-sm font-medium">دانلود</a>
                        </li>
                        @endforeach
                    </ul>
                @else
                    <div class="text-center py-6 text-slate-500 text-sm">
                        مدرکی برای این وظیفه بارگذاری نشده است.
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- سایدبار اطلاعات تکمیلی و عملیات -->
    <div class="space-y-6">
        <!-- تایمر و SLA -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="border-b border-slate-200 px-6 py-4">
                <h3 class="font-bold text-slate-800">وضعیت SLA</h3>
            </div>
            <div class="p-6 space-y-4">
                @if($task->sla_started_at)
                    <div class="bg-blue-50 text-blue-800 px-4 py-3 rounded-lg border border-blue-100">
                        <p class="text-sm font-medium mb-1">زمان سپری شده از ارجاع:</p>
                        <p class="text-2xl font-bold" dir="ltr">
                            {{ \Carbon\Carbon::parse($task->sla_started_at)->diffForHumans(null, true) }}
                        </p>
                    </div>
                @else
                    <div class="bg-slate-50 text-slate-500 px-4 py-3 rounded-lg border border-slate-100 text-sm text-center">
                        تایمر SLA هنوز فعال نشده است.
                    </div>
                @endif
                
                @if(!$task->sla_stopped_at && $task->status !== 'completed' && auth()->user()->contractor_id === $task->contractor_id)
                    <form action="{{ route('tasks.submit', $task->id) }}" method="POST">
                        @csrf
                        <button type="submit" class="w-full bg-green-600 text-white font-medium py-2 px-4 rounded-lg hover:bg-green-700 transition">
                            اعلام اتمام وظیفه (توقف SLA)
                        </button>
                    </form>
                @endif
            </div>
        </div>

        <!-- کارتابل ناظر -->
        @if(auth()->user()->contractor_id === null)
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="border-b border-slate-200 px-6 py-4">
                <h3 class="font-bold text-slate-800">کارتابل نظارت</h3>
            </div>
            <div class="p-6 space-y-4">
                <form action="{{ route('approvals.store', $task->id) }}" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">وضعیت تایید</label>
                        <select name="status" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="approved">تایید نهایی</option>
                            <option value="rejected">رد (نیاز به اصلاح)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">توضیحات</label>
                        <textarea name="comments" rows="3" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="دلایل تایید یا رد را بنویسید..."></textarea>
                    </div>
                    <button type="submit" class="w-full bg-blue-600 text-white font-medium py-2 px-4 rounded-lg hover:bg-blue-700 transition">
                        ثبت نظر نظارتی
                    </button>
                </form>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
