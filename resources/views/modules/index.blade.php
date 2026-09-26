@extends('layouts.app')

@section('title', 'ساختار ماژول‌ها - TMS')
@section('header_title', 'ساختار ماژول پروژه‌ها')

@section('content')
<div class="max-w-6xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-slate-800">ساختار ماژول‌ها</h1>
    </div>

    @foreach ($projects as $project)
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200 bg-slate-50 flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-bold text-slate-800">{{ $project->name }}</h2>
                    <p class="text-sm text-slate-500">پروژه #{{ $project->id }}</p>
                </div>
                <a href="{{ route('modules.show', $project->id) }}"
                   class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium transition">
                    مدیریت ساختار ماژول
                </a>
            </div>

            <div class="p-6">
                @if ($project->activeModules->isEmpty())
                    <p class="text-sm text-slate-500 py-4 text-center">هنوز ماژول فعالی برای این پروژه تعریف نشده است.</p>
                @else
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        @foreach ($project->activeModules as $module)
                            <div class="border border-slate-200 rounded-lg p-4">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="font-bold text-slate-800">{{ $module->name }}</span>
                                    <span class="text-sm bg-blue-100 text-blue-700 rounded-full px-2 py-0.5">{{ number_format($module->weight, 2) }}%</span>
                                </div>
                                <p class="text-xs text-slate-500">{{ $module->stages->count() }} مرحله استاندارد</p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @endforeach

    @if ($projects->isEmpty())
        <div class="bg-white rounded-xl border border-slate-200 p-8 text-center text-slate-500">
            هیچ پروژه‌ای ثبت نشده است.
        </div>
    @endif
</div>
@endsection
