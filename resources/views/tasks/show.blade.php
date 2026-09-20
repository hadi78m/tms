@extends('layouts.app')

@section('title', 'جزئیات وظیفه: ' . $task->title)

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- ستون اصلی اطلاعات -->
    <div class="lg:col-span-2 space-y-6">
        <!-- فلش پیام‌ها -->
        @if(session('status'))
            <div class="bg-green-50 border border-green-200 text-green-800 px-5 py-4 rounded-xl flex items-start gap-3">
                <svg class="w-5 h-5 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                <span>{{ session('status') }}</span>
            </div>
        @endif
        @if(session('error'))
            <div class="bg-red-50 border border-red-200 text-red-800 px-5 py-4 rounded-xl flex items-start gap-3">
                <svg class="w-5 h-5 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm-1-9a1 1 0 012 0v4a1 1 0 01-2 0V9zm1-5a1 1 0 100 2 1 1 0 000-2z" clip-rule="evenodd"/></svg>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        <!-- کارت مشخصات اصلی -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="border-b border-slate-200 px-6 py-4 flex items-center justify-between">
                <h2 class="text-xl font-bold text-slate-800">مشخصات وظیفه #{{ $task->id }}</h2>
                @php
                    $statusColors = [
                        'draft'              => 'bg-slate-100 text-slate-700',
                        'assigned'           => 'bg-blue-100 text-blue-800',
                        'in_progress'        => 'bg-yellow-100 text-yellow-800',
                        'submitted_for_review' => 'bg-orange-100 text-orange-800',
                        'under_review'       => 'bg-purple-100 text-purple-800',
                        'supervisor_approved'=> 'bg-indigo-100 text-indigo-800',
                        'approved'           => 'bg-green-100 text-green-800',
                        'needs_rework'       => 'bg-red-100 text-red-800',
                        'cancelled'          => 'bg-slate-100 text-slate-500',
                    ];
                    $statusLabels = [
                        'draft'              => 'پیش‌نویس',
                        'assigned'           => 'ارجاع شده',
                        'in_progress'        => 'در حال انجام',
                        'submitted_for_review' => 'ارائه شده برای بررسی',
                        'under_review'       => 'در حال بررسی',
                        'supervisor_approved'=> 'تایید ناظر',
                        'approved'           => 'تایید نهایی',
                        'needs_rework'       => 'نیاز به اصلاح',
                        'cancelled'          => 'لغو شده',
                    ];
                    $colorClass = $statusColors[$task->status] ?? 'bg-slate-100 text-slate-700';
                    $statusLabel = $statusLabels[$task->status] ?? $task->status;
                @endphp
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $colorClass }}">
                    {{ $statusLabel }}
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
                <form action="{{ route('documents.store', $task->id) }}" method="POST" enctype="multipart/form-data" class="flex items-center gap-2">
                    @csrf
                    <input type="text" name="claimed_at" placeholder="تاریخ شمسی: ۱۴۰۵/۰۱/۱۵" autocomplete="off" class="datedown text-xs px-2 py-1 border border-slate-300 rounded focus:ring-blue-500 focus:border-blue-500 text-left" dir="ltr" title="زمان ادعایی انجام کار (شمسی)">
                    <input type="file" name="file" required class="text-xs w-48 text-slate-500 file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:text-xs file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    <button type="submit" class="text-sm text-blue-600 font-medium hover:text-blue-800 bg-blue-50 px-3 py-1 rounded transition">
                        + بارگذاری سند
                    </button>
                </form>
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
                                    <p class="font-medium text-slate-800">{{ $doc->original_name ?? $doc->file_name }}</p>
                                    <p class="text-xs text-slate-500 mt-1" dir="ltr">
                                        Sys: {{ jdate($doc->recorded_at ?? $doc->created_at)->format('Y/m/d H:i') }} | {{ round($doc->size / 1024, 2) }} KB
                                        @if($doc->claimed_at)
                                        <span class="mx-1">|</span> <span class="text-amber-600 font-medium" title="Claimed Time">Claim: {{ jdate($doc->claimed_at)->format('Y/m/d H:i') }}</span>
                                        @endif
                                    </p>
                                    @if($doc->file_hash)
                                    <p class="text-[10px] text-slate-400 font-mono mt-1 flex items-center gap-1" dir="ltr" title="SHA-256 Hash">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364 0-1.457.39-2.823 1.07-4"/></svg>
                                        {{ substr($doc->file_hash, 0, 16) }}...
                                    </p>
                                    @endif
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
        <!-- زیرتسک‌ها -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="border-b border-slate-200 px-6 py-4 flex justify-between items-center">
                <h3 class="text-lg font-bold text-slate-800">زیرتسک‌ها (Subtasks)</h3>
                @if(auth()->user()->hasRole(['project_manager', 'admin']))
                <a href="{{ route('tasks.create', ['parent_id' => $task->id]) }}" class="text-sm text-blue-600 font-medium hover:text-blue-800 bg-blue-50 px-3 py-1 rounded transition">
                    + افزودن زیرتسک
                </a>
                @endif
            </div>
            <div class="p-6">
                @if($task->childTasks && $task->childTasks->count() > 0)
                    <ul class="divide-y divide-slate-100">
                        @foreach($task->childTasks as $child)
                        <li class="py-3 flex justify-between items-center">
                            <div>
                                <a href="{{ route('tasks.show', $child->id) }}" class="font-medium text-slate-800 hover:text-blue-600">#{{ $child->id }} - {{ $child->title }}</a>
                                <p class="text-xs text-slate-500 mt-1">وضعیت: {{ __('status.' . $child->status) ?? $child->status }}</p>
                            </div>
                        </li>
                        @endforeach
                    </ul>
                @else
                    <div class="text-center py-6 text-slate-500 text-sm">
                        این وظیفه هیچ زیرتسکی ندارد.
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- سایدبار اطلاعات تکمیلی و عملیات -->
    <div class="space-y-6">
        <!-- پیش‌نیازها (Dependencies) -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="border-b border-slate-200 px-6 py-4">
                <h3 class="font-bold text-slate-800 flex items-center gap-2">
                    پیش‌نیازها
                    @php
                        $isBlocked = $task->dependenciesAsSuccessor()->whereHas('predecessor', function($q) {
                            $q->where('status', '!=', 'approved');
                        })->exists();
                    @endphp
                    @if($isBlocked)
                        <span class="text-xs bg-red-100 text-red-800 px-2 py-0.5 rounded-full flex items-center gap-1" title="قفل شده (پیش‌نیازها تکمیل نشده‌اند)">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                            مسدود
                        </span>
                    @endif
                </h3>
            </div>
            <div class="p-6">
                @if($task->dependenciesAsSuccessor && $task->dependenciesAsSuccessor->count() > 0)
                    <ul class="space-y-3 mb-4">
                        @foreach($task->dependenciesAsSuccessor as $dep)
                            <li class="flex justify-between items-center bg-slate-50 p-2 rounded border border-slate-100">
                                <a href="{{ route('tasks.show', $dep->predecessor_task_id) }}" class="text-sm font-medium text-slate-800 hover:text-blue-600">
                                    #{{ $dep->predecessor_task_id }} - {{ Str::limit($dep->predecessor->title ?? '', 20) }}
                                </a>
                                <div class="flex items-center gap-2">
                                    <span class="text-xs px-2 py-0.5 rounded-full {{ $dep->predecessor->status === 'approved' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                                        {{ $dep->predecessor->status === 'approved' ? 'تایید شده' : 'ناتمام' }}
                                    </span>
                                    @if(auth()->user()->hasRole(['project_manager', 'admin']))
                                    <form action="{{ route('tasks.dependencies.destroy', [$task->id, $dep->id]) }}" method="POST" onsubmit="return confirm('آیا از حذف این پیش‌نیاز مطمئن هستید؟');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-500 hover:text-red-700">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </form>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-sm text-slate-500 mb-4">هیچ پیش‌نیازی تعریف نشده است.</p>
                @endif

                @if(auth()->user()->hasRole(['project_manager', 'admin']))
                <form action="{{ route('tasks.dependencies.store', $task->id) }}" method="POST" class="flex gap-2">
                    @csrf
                    <input type="number" name="depends_on_task_id" placeholder="شناسه وظیفه (ID)" required class="w-full text-sm px-3 py-1.5 border border-slate-300 rounded focus:ring-blue-500 focus:border-blue-500">
                    <button type="submit" class="bg-blue-600 text-white px-3 py-1.5 rounded text-sm hover:bg-blue-700 transition">افزودن</button>
                </form>
                @endif
            </div>
        </div>

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

                @if($task->status === 'assigned' && auth()->user()->contractor_id === $task->contractor_id)
                    <form action="{{ route('tasks.start', $task->id) }}" method="POST" class="mb-3">
                        @csrf
                        <button type="submit" class="w-full bg-blue-600 text-white font-medium py-2 px-4 rounded-lg hover:bg-blue-700 transition {{ $isBlocked ?? false ? 'opacity-50 cursor-not-allowed' : '' }}" {{ $isBlocked ?? false ? 'disabled' : '' }}>
                            @if($isBlocked ?? false)
                                🔒 امکان شروع نیست (پیش‌نیاز دارد)
                            @else
                                شروع وظیفه
                            @endif
                        </button>
                    </form>
                @endif

                @if(!$task->sla_stopped_at && $task->status === 'in_progress' && auth()->user()->contractor_id === $task->contractor_id)
                    <form action="{{ route('tasks.submit', $task->id) }}" method="POST">
                        @csrf
                        <button type="submit" class="w-full bg-green-600 text-white font-medium py-2 px-4 rounded-lg hover:bg-green-700 transition">
                            اعلام اتمام وظیفه (توقف SLA)
                        </button>
                    </form>
                @endif
            </div>
        </div>

        {{-- ─── کارتابل تایید فنی ناظر ─── --}}
        {{-- نمایش فقط برای دارندگان مجوز technical_approval و فقط در وضعیت under_review --}}
        @can('technical_approval')
            @if($task->status === 'under_review')
            <div class="bg-white rounded-xl shadow-sm border border-purple-200 overflow-hidden">
                <div class="border-b border-purple-200 bg-purple-50 px-6 py-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <h3 class="font-bold text-purple-800">تایید فنی ناظر</h3>
                </div>
                <div class="p-6 space-y-4">
                    {{-- دکمه تایید فنی --}}
                    <form action="{{ route('approvals.store', $task->id) }}" method="POST" id="form-technical-approve">
                        @csrf
                        <input type="hidden" name="approval_type" value="technical">
                        <input type="hidden" name="status" value="approved">
                        <button type="submit"
                                id="btn-technical-approve"
                                class="w-full bg-purple-600 text-white font-medium py-2.5 px-4 rounded-lg hover:bg-purple-700 transition flex items-center justify-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            تایید فنی (ناظر)
                        </button>
                    </form>

                    {{-- فرم نیاز به اصلاح توسط ناظر --}}
                    <details class="group">
                        <summary class="cursor-pointer text-sm font-medium text-red-600 hover:text-red-800 flex items-center gap-1 list-none">
                            <svg class="w-4 h-4 transition group-open:rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                            اعلام نیاز به اصلاح
                        </summary>
                        <form action="{{ route('approvals.store', $task->id) }}" method="POST" class="mt-3 space-y-3" id="form-technical-rework">
                            @csrf
                            <input type="hidden" name="approval_type" value="technical">
                            <input type="hidden" name="status" value="needs_rework">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">دلیل نیاز به اصلاح <span class="text-red-500">*</span></label>
                                <textarea name="comment"
                                          id="technical-rework-comment"
                                          rows="3"
                                          required
                                          class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-red-400 focus:border-red-400"
                                          placeholder="دلیل بازگشت برای اصلاح را بنویسید..."></textarea>
                            </div>
                            <button type="submit"
                                    id="btn-technical-rework"
                                    class="w-full bg-red-600 text-white font-medium py-2 px-4 rounded-lg hover:bg-red-700 transition text-sm">
                                بازگشت برای اصلاح
                            </button>
                        </form>
                    </details>
                </div>
            </div>
            @endif
        @endcan

        {{-- ─── کارتابل تایید نهایی بهره‌بردار ─── --}}
        {{-- نمایش فقط برای دارندگان مجوز final_approval و فقط در وضعیت supervisor_approved --}}
        @can('final_approval')
            @if($task->status === 'supervisor_approved')
            <div class="bg-white rounded-xl shadow-sm border border-green-200 overflow-hidden">
                <div class="border-b border-green-200 bg-green-50 px-6 py-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                    </svg>
                    <h3 class="font-bold text-green-800">تایید نهایی بهره‌بردار</h3>
                </div>
                <div class="p-6 space-y-4">
                    <div class="text-xs text-green-700 bg-green-50 border border-green-100 rounded-lg px-3 py-2">
                        ✓ تایید فنی ناظر در {{ $task->supervisor_approved_at ? jdate($task->supervisor_approved_at)->format('Y/m/d H:i') : '-' }} انجام شده است.
                    </div>

                    {{-- دکمه تایید نهایی --}}
                    <form action="{{ route('approvals.store', $task->id) }}" method="POST" id="form-final-approve">
                        @csrf
                        <input type="hidden" name="approval_type" value="final">
                        <input type="hidden" name="status" value="approved">
                        <button type="submit"
                                id="btn-final-approve"
                                class="w-full bg-green-600 text-white font-medium py-2.5 px-4 rounded-lg hover:bg-green-700 transition flex items-center justify-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            تایید نهایی (بهره‌بردار)
                        </button>
                    </form>

                    {{-- فرم نیاز به اصلاح توسط بهره‌بردار --}}
                    <details class="group">
                        <summary class="cursor-pointer text-sm font-medium text-red-600 hover:text-red-800 flex items-center gap-1 list-none">
                            <svg class="w-4 h-4 transition group-open:rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                            رد و اعلام نیاز به اصلاح
                        </summary>
                        <form action="{{ route('approvals.store', $task->id) }}" method="POST" class="mt-3 space-y-3" id="form-final-rework">
                            @csrf
                            <input type="hidden" name="approval_type" value="final">
                            <input type="hidden" name="status" value="needs_rework">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">دلیل رد و اصلاح <span class="text-red-500">*</span></label>
                                <textarea name="comment"
                                          id="final-rework-comment"
                                          rows="3"
                                          required
                                          class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-red-400 focus:border-red-400"
                                          placeholder="دلیل رد یا نیاز به اصلاح را بنویسید..."></textarea>
                            </div>
                            <button type="submit"
                                    id="btn-final-rework"
                                    class="w-full bg-red-600 text-white font-medium py-2 px-4 rounded-lg hover:bg-red-700 transition text-sm">
                                رد و بازگشت برای اصلاح
                            </button>
                        </form>
                    </details>
                </div>
            </div>
            @endif
        @endcan
    </div>
</div>
@endsection
