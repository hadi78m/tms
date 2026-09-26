@extends('layouts.app')

@section('title', 'فاز WBS: ' . $phase->name . ' - TMS')
@section('header_title', 'فاز WBS')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-slate-800">{{ $phase->name }}</h1>
        <a href="{{ route('wbs-phases.index') }}" class="text-sm text-blue-600 hover:underline">بازگشت به فهرست</a>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 p-6 grid grid-cols-2 gap-4 text-sm">
        <div><span class="text-slate-500">پروژه:</span> {{ $phase->project->name ?? '-' }}</div>
        <div>
            <span class="text-slate-500">وضعیت:</span>
            @if ($phase->completion_status === 'pending')
                تکمیل‌نشده
            @elseif ($phase->completion_status === 'completed')
                <span class="text-green-700 font-bold">تکمیل‌شده</span>
            @else
                <span class="text-red-700 font-bold">تکمیل‌نشده (اعلام ناظر)</span>
            @endif
        </div>
        <div><span class="text-slate-500">خروجی مورد انتظار:</span> {{ $phase->expected_output }}</div>
        @if ($phase->supervisor_comment)
            <div class="col-span-2"><span class="text-slate-500">یادداشت ناظر:</span> {{ $phase->supervisor_comment }}</div>
        @endif
    </div>

    <!-- Checklist -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 font-bold text-slate-800 bg-slate-50">چک‌لیست خروجی‌ها</div>
        @if ($phase->checklistItems->isEmpty())
            <p class="px-6 py-8 text-center text-slate-400">آیتمی در چک‌لیست ثبت نشده است.</p>
        @else
            <ul class="divide-y divide-slate-100">
                @foreach ($phase->checklistItems as $item)
                    <li class="px-6 py-4 flex items-center justify-between">
                        <div>
                            <span class="font-medium text-slate-800">{{ $item->title }}</span>
                            @if ($item->description)
                                <p class="text-xs text-slate-500">{{ $item->description }}</p>
                            @endif
                            @if ($item->is_completed)
                                <p class="text-xs text-green-700 mt-1">
                                    تکمیل‌شده @if($item->completedByUser) توسط {{ $item->completedByUser->name }} @endif
                                </p>
                            @endif
                        </div>
                        <div class="flex gap-2">
                            @if ($item->is_completed)
                                <form method="POST" action="{{ route('wbs-phases.checklist-items.reopen', [$phase->id, $item->id]) }}">
                                    @csrf
                                    <button type="submit" class="px-3 py-1.5 bg-slate-200 text-slate-700 rounded-lg text-xs hover:bg-slate-300">بازگشایی</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('wbs-phases.checklist-items.complete', [$phase->id, $item->id]) }}">
                                    @csrf
                                    <button type="submit" class="px-3 py-1.5 bg-green-600 text-white rounded-lg text-xs hover:bg-green-700">تکمیل</button>
                                </form>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
        <form method="POST" action="{{ route('wbs-phases.checklist-items.store', $phase->id) }}" class="p-6 border-t border-slate-100 flex flex-wrap items-end gap-3">
            @csrf
            <div class="flex-1 min-w-48">
                <label class="block text-sm font-medium text-slate-700 mb-1">آیتم جدید</label>
                <input type="text" name="title" maxlength="255" class="w-full rounded-lg border-slate-300" required>
            </div>
            <div class="flex-1 min-w-48">
                <label class="block text-sm font-medium text-slate-700 mb-1">توضیح (اختیاری)</label>
                <input type="text" name="description" maxlength="2000" class="w-full rounded-lg border-slate-300">
            </div>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium transition">افزودن</button>
        </form>
    </div>

    <!-- Supervisor decision -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 font-bold text-slate-800 bg-slate-50">تصمیم ناظر پروژه</div>
        <form method="POST" action="{{ route('wbs-phases.decide', $phase->id) }}" class="p-6 space-y-4">
            @csrf
            <div class="flex flex-wrap gap-4 text-sm">
                <label class="inline-flex items-center gap-2">
                    <input type="radio" name="decision" value="complete" {{ $phase->completion_status !== 'pending' ? 'disabled' : '' }} required>
                    اعلام تکمیل فاز
                </label>
                <label class="inline-flex items-center gap-2">
                    <input type="radio" name="decision" value="not_completed" {{ $phase->completion_status !== 'pending' ? 'disabled' : '' }}>
                    اعلام تکمیل‌نشده (با دلیل)
                </label>
                <label class="inline-flex items-center gap-2">
                    <input type="radio" name="decision" value="reopen" {{ $phase->completion_status === 'pending' ? 'disabled' : '' }}>
                    بازگشایی فاز
                </label>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">یادداشت / دلیل (برای تکمیل‌نشده الزامی)</label>
                <input type="text" name="comment" maxlength="2000" class="w-full rounded-lg border-slate-300">
            </div>
            <button type="submit" class="px-5 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 font-medium transition">ثبت تصمیم</button>
        </form>
    </div>
</div>
@endsection
