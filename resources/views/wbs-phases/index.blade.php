@extends('layouts.app')

@section('title', 'فازهای WBS - TMS')
@section('header_title', 'فازهای WBS')

@section('content')
<div class="max-w-6xl mx-auto space-y-6">
    <h1 class="text-2xl font-bold text-slate-800">فازهای WBS</h1>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 bg-slate-50">
            <h2 class="text-lg font-bold text-slate-800">ایجاد فاز جدید</h2>
            <p class="text-sm text-slate-500 mt-1">
                خروجی واقعی فاز، چک‌لیست آن است. فازهای جدید به‌صورت خودکار در وضعیت «در انتظار» ایجاد می‌شوند.
            </p>
        </div>
        <form method="POST" action="{{ route('wbs-phases.store') }}" class="p-6 grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
            @csrf
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">پروژه</label>
                <select name="project_id" class="w-full rounded-lg border-slate-300" required>
                    @foreach ($projects as $project)
                        <option value="{{ $project->id }}">{{ $project->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">نام فاز</label>
                <input type="text" name="name" class="w-full rounded-lg border-slate-300" required maxlength="255">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">وزن (Legacy)</label>
                <input type="number" name="weight" step="0.01" min="0" max="100" value="0" class="w-full rounded-lg border-slate-300" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">مدت برنامه‌ریزی‌شده</label>
                <input type="number" name="planned_duration" min="1" class="w-full rounded-lg border-slate-300" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">واحد مدت</label>
                <input type="text" name="duration_unit" value="day" class="w-full rounded-lg border-slate-300" required maxlength="50">
            </div>
            <div>
                <button type="submit" class="px-5 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium transition">ایجاد فاز</button>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 font-bold text-slate-800 bg-slate-50">فهرست فازها</div>
        @if ($phases->isEmpty())
            <p class="px-6 py-8 text-center text-slate-400">هیچ فازی ثبت نشده است.</p>
        @else
            <ul class="divide-y divide-slate-100">
                @foreach ($phases as $phase)
                    <li class="px-6 py-4 flex items-center justify-between">
                        <div>
                            <a href="{{ route('wbs-phases.show', $phase->id) }}" class="font-medium text-blue-600 hover:underline">
                                {{ $phase->name }}
                            </a>
                            <span class="text-xs text-slate-400 mr-2">{{ $phase->project->name ?? '-' }}</span>
                        </div>
                        <div class="flex items-center gap-3 text-xs">
                            <span class="text-slate-500">{{ $phase->checklistItems->count() }} آیتم چک‌لیست</span>
                            @if ($phase->completion_status === 'pending')
                                <span class="bg-amber-100 text-amber-800 rounded-full px-2 py-0.5">در انتظار</span>
                            @elseif ($phase->completion_status === 'completed')
                                <span class="bg-green-100 text-green-800 rounded-full px-2 py-0.5">تکمیل‌شده</span>
                            @else
                                <span class="bg-red-100 text-red-800 rounded-full px-2 py-0.5">تکمیل‌نشده</span>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
@endsection
