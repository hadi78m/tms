@extends('layouts.app')

@section('title', 'مرحله: ' . $stage->name . ' - TMS')
@section('header_title', 'مرحله: ' . $stage->name)

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">{{ $stage->name }}</h1>
            <p class="text-sm text-slate-500">
                ماژول {{ $module->name }} · پروژه {{ $module->project->name ?? '-' }}
            </p>
        </div>
        <a href="{{ route('modules.show', $module->project_id) }}" class="text-sm text-blue-600 hover:underline">بازگشت به ساختار ماژول</a>
    </div>

    <!-- Stage status -->
    <div class="bg-white rounded-xl border border-slate-200 p-6 grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
        <div>
            <span class="text-slate-500">وزن تخصیص‌یافته:</span>
            <span class="font-bold text-slate-800">{{ number_format($stage->weight, 2) }}٪</span>
        </div>
        <div>
            <span class="text-slate-500">مبلغ تأییدشدهٔ مراحل:</span>
            <span class="font-bold text-green-700">{{ number_format($approvedWeight, 2) }}٪</span>
        </div>
        <div>
            <span class="text-slate-500">باقی‌مانده:</span>
            <span class="font-bold text-slate-800">{{ number_format($remainingWeight, 2) }}٪</span>
        </div>
        <div>
            <span class="text-slate-500">وضعیت وزن:</span>
            @if ($isWeightLocked)
                <span class="font-bold text-amber-700">قفل‌شده (پس از اولین ثبت)</span>
            @else
                <span class="font-bold text-green-700">قابل ویرایش</span>
            @endif
        </div>
    </div>

    <!-- Weight rebalance -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 bg-slate-50">
            <h2 class="text-lg font-bold text-slate-800">توزیع وزن مراحل ماژول</h2>
            <p class="text-sm text-slate-500 mt-1">
                مجموع وزن نُه مرحله باید ۱۰۰٪ بماند. وزن مرحله‌ای که حتی یک رکورد تأیید پیشرفت دارد، قفل است.
            </p>
        </div>
        <form method="POST" action="{{ route('modules.stages.rebalance', $module->id) }}" class="p-6 space-y-3">
            @csrf
            @php $anyLocked = false; @endphp
            @foreach ($module->stages as $s)
                @php
                    $locked = $s->isWeightLocked();
                    $anyLocked = $anyLocked || $locked;
                @endphp
                <div class="flex items-center gap-3">
                    <span class="text-sm {{ $locked ? 'text-amber-700' : 'text-slate-700' }} w-48">
                        {{ $s->name }} @if($locked) <span title="قفل‌شده">🔒</span> @endif
                    </span>
                    <input type="number" name="weights[{{ $s->id }}]" value="{{ $s->weight }}" step="0.01" min="0.01" max="100"
                           @if($locked) disabled @endif
                           class="rounded-lg border-slate-300 {{ $locked ? 'bg-slate-100' : '' }}" required>
                    <span class="text-sm text-slate-400">٪</span>
                </div>
            @endforeach
            <div class="text-left">
                <button type="submit" class="px-5 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium transition">
                    اعمال توزیع جدید
                </button>
                @if ($anyLocked)
                    <p class="text-xs text-amber-700 mt-2">مراحل قفل‌شده از به‌روزرسانی مستثنی هستند.</p>
                @endif
            </div>
        </form>
    </div>

    <!-- Proposal form -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 bg-slate-50">
            <h2 class="text-lg font-bold text-slate-800">ثبت پیشنهاد پیشرفت مرحله</h2>
        </div>
        <form method="POST" action="{{ route('stages.progress-approvals.store', $stage->id) }}" class="p-6 grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
            @csrf
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">مبلغ پیشنهادی (٪ وزن مرحله)</label>
                <input type="number" name="proposed_amount" step="0.01" min="0.01" max="{{ $stage->weight }}"
                       class="w-full rounded-lg border-slate-300" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">دلیل / توضیح (اختیاری)</label>
                <input type="text" name="reason" maxlength="1000" class="w-full rounded-lg border-slate-300">
            </div>
            <div>
                <button type="submit" class="px-5 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 font-medium transition">
                    ثبت پیشنهاد
                </button>
            </div>
        </form>
    </div>

    <!-- Approval history -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 font-bold text-slate-800 bg-slate-50">تاریخچه تأییدهای پیشرفت</div>
        @if ($stage->approvals->isEmpty())
            <p class="px-6 py-8 text-center text-slate-400">هنوز هیچ رکورد تأیید پیشرفتی برای این مرحله ثبت نشده است.</p>
        @else
            <ul class="divide-y divide-slate-100">
                @foreach ($stage->approvals->sortByDesc('id') as $approval)
                    <li class="px-6 py-4">
                        <div class="flex flex-wrap items-center gap-3 text-sm">
                            <span class="font-bold text-slate-800">#{{ $approval->id }}</span>

                            @if ($approval->isPending() && ! $approval->isSuperseded())
                                <span class="bg-amber-100 text-amber-800 rounded-full px-2 py-0.5 text-xs">در انتظار تصمیم</span>
                            @elseif ($approval->status === 'approved')
                                <span class="bg-green-100 text-green-800 rounded-full px-2 py-0.5 text-xs">تأییدشده</span>
                            @elseif ($approval->status === 'rejected')
                                <span class="bg-red-100 text-red-800 rounded-full px-2 py-0.5 text-xs">ردشده</span>
                            @endif

                            @if ($approval->isSuperseded())
                                <span class="bg-slate-200 text-slate-600 rounded-full px-2 py-0.5 text-xs">باطل‌شده (تاریخچه)</span>
                            @endif

                            <span class="text-slate-500">پیشنهاد: {{ number_format($approval->proposed_amount, 2) }}٪</span>

                            @if ($approval->status === 'approved')
                                <span class="text-green-700 font-medium">مبلغ تأییدشده: {{ number_format($approval->approved_amount, 2) }}٪</span>
                            @endif

                            @if ($approval->supersedes_approval_id)
                                <span class="text-slate-400 text-xs">اصلاحِ رکورد #{{ $approval->supersedes_approval_id }}</span>
                            @endif
                        </div>

                        @if ($approval->reason)
                            <p class="text-xs text-slate-500 mt-1">دلیل: {{ $approval->reason }}</p>
                        @endif

                        <!-- Available actions per state -->
                        @if ($approval->isPending() && ! $approval->isSuperseded())
                            <details class="mt-2">
                                <summary class="text-xs text-blue-600 cursor-pointer">اقدام</summary>
                                <div class="mt-2 space-y-3">
                                    <form method="POST" action="{{ route('stages.progress-approvals.adjust', $approval->id) }}"
                                          class="flex flex-wrap items-center gap-2">
                                        @csrf
                                        <input type="number" name="proposed_amount" value="{{ $approval->proposed_amount }}"
                                               step="0.01" min="0.01" max="100" class="rounded-lg border-slate-300 text-sm" required>
                                        <input type="text" name="reason" placeholder="دلیل اصلاح" maxlength="1000"
                                               class="rounded-lg border-slate-300 text-sm">
                                        <button type="submit" class="px-3 py-1.5 bg-slate-700 text-white rounded-lg text-xs hover:bg-slate-800">اصلاح مبلغ پیشنهادی</button>
                                    </form>

                                    <form method="POST" action="{{ route('stages.progress-approvals.decide', $approval->id) }}"
                                          class="flex flex-wrap items-center gap-2">
                                        @csrf
                                        <input type="hidden" name="decision" value="approve">
                                        <label class="text-xs text-slate-600">مبلغ تأیید (≤ پیشنهاد):</label>
                                        <input type="number" name="approved_amount" step="0.01" min="0" max="{{ $approval->proposed_amount }}"
                                               value="{{ $approval->proposed_amount }}" class="rounded-lg border-slate-300 text-sm" required>
                                        <input type="text" name="reason" placeholder="توضیح (اختیاری)" maxlength="1000" class="rounded-lg border-slate-300 text-sm">
                                        <button type="submit" class="px-3 py-1.5 bg-green-600 text-white rounded-lg text-xs hover:bg-green-700">تأیید</button>
                                    </form>

                                    <form method="POST" action="{{ route('stages.progress-approvals.decide', $approval->id) }}"
                                          class="flex flex-wrap items-center gap-2">
                                        @csrf
                                        <input type="hidden" name="decision" value="reject">
                                        <input type="text" name="reason" placeholder="دلیل رد (الزامی)" maxlength="1000"
                                               class="rounded-lg border-slate-300 text-sm" required>
                                        <button type="submit" class="px-3 py-1.5 bg-red-600 text-white rounded-lg text-xs hover:bg-red-700">رد</button>
                                    </form>

                                    @if ($approval->status === 'approved' || $approval->status === 'rejected')
                                        <form method="POST" action="{{ route('stages.progress-approvals.store', $stage->id) }}"
                                              class="flex flex-wrap items-center gap-2 border-t border-slate-100 pt-2">
                                            @csrf
                                            <input type="hidden" name="supersedes_approval_id" value="{{ $approval->id }}">
                                            <span class="text-xs text-slate-600">اصلاح رکورد تصمیم‌گرفته (جایگزینی — نه افزودن):</span>
                                            <input type="number" name="proposed_amount" step="0.01" min="0.01" max="100"
                                                   class="rounded-lg border-slate-300 text-sm" required>
                                            <input type="text" name="reason" placeholder="دلیل" maxlength="1000" class="rounded-lg border-slate-300 text-sm">
                                            <button type="submit" class="px-3 py-1.5 bg-amber-600 text-white rounded-lg text-xs hover:bg-amber-700">ثبت جایگزین</button>
                                        </form>
                                    @endif
                                </div>
                            </details>
                        @elseif ($approval->status === 'approved' || $approval->status === 'rejected')
                            @if (! $approval->isSuperseded())
                                <details class="mt-2">
                                    <summary class="text-xs text-amber-700 cursor-pointer">اصلاح این تصمیم (ثبت جایگزین)</summary>
                                    <form method="POST" action="{{ route('stages.progress-approvals.store', $stage->id) }}"
                                          class="mt-2 flex flex-wrap items-center gap-2">
                                        @csrf
                                        <input type="hidden" name="supersedes_approval_id" value="{{ $approval->id }}">
                                        <input type="number" name="proposed_amount" step="0.01" min="0.01" max="100"
                                               class="rounded-lg border-slate-300 text-sm" required>
                                        <input type="text" name="reason" placeholder="دلیل اصلاح" maxlength="1000" class="rounded-lg border-slate-300 text-sm">
                                        <button type="submit" class="px-3 py-1.5 bg-amber-600 text-white rounded-lg text-xs hover:bg-amber-700">ثبت جایگزین</button>
                                    </form>
                                </details>
                            @endif
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <!-- Tasks attached to this stage -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 font-bold text-slate-800 bg-slate-50">وظایف این مرحله</div>
        @if ($stage->tasks->isEmpty())
            <p class="px-6 py-8 text-center text-slate-400">وظیفه‌ای به این مرحله متصل نیست.</p>
        @else
            <ul class="divide-y divide-slate-100">
                @foreach ($stage->tasks as $task)
                    <li class="px-6 py-3 flex items-center justify-between text-sm">
                        <a href="{{ route('tasks.show', $task->id) }}" class="text-blue-600 hover:underline">{{ $task->title }}</a>
                        <span class="text-slate-500">{{ $task->status }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
@endsection
