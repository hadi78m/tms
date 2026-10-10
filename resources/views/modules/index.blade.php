@extends('layouts.app')

@section('title', 'ساختار ماژول‌ها - TMS')
@section('header_title', 'ساختار ماژول پروژه‌ها')

@section('content')
{{-- Phase 5B-8 — Visual Migration (فهرست ساختار ماژول پروژه‌ها).
     فقط زبان بصری منتقل شده است: route (modules.index / modules.show)،
     حلقهٔ $projects و activeModules، فرمت وزن (number_format 2 رقم) و
     متن‌های حالت خالی عیناً حفظ شده‌اند؛ صفحه strictly read-only است
     (هیچ فرم/JS/فیلتر/صفحه‌بندی وجود ندارد) و فیلتر Project Scope
     سمت کنترلر بدون تغییر مانده است. --}}

<div class="mx-auto max-w-6xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold text-ink">ساختار ماژول‌ها</h1>
    </div>

    @foreach ($projects as $project)
        <x-ui.panel body-class="p-5 sm:p-6">
            <x-slot:header>
                <header class="flex flex-wrap items-center justify-between gap-3 border-b border-line-subtle px-5 py-3.5 sm:px-6">
                    <div>
                        <h2 class="text-lg font-extrabold leading-tight text-ink">{{ $project->name }}</h2>
                        <p class="mt-0.5 text-sm text-ink-muted">پروژه #{{ $project->id }}</p>
                    </div>
                    <a href="{{ route('modules.show', $project->id) }}"
                       class="rounded-ui bg-primary px-4 py-2 text-sm font-bold text-white shadow-ui-soft transition hover:bg-primary-hover focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                        مدیریت ساختار ماژول
                    </a>
                </header>
            </x-slot:header>

            @if ($project->activeModules->isEmpty())
                <x-ui.empty-state title="هنوز ماژول فعالی برای این پروژه تعریف نشده است.">
                </x-ui.empty-state>
            @else
                <ul class="grid grid-cols-1 gap-4 md:grid-cols-3" role="list">
                    @foreach ($project->activeModules as $module)
                        <li class="rounded-ui border border-line-subtle bg-surface-base p-4">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="text-sm font-bold text-ink">{{ $module->name }}</span>
                                <x-ui.badge variant="info">{{ number_format($module->weight, 2) }}%</x-ui.badge>
                            </div>
                            <p class="mt-2 text-xs text-ink-muted">{{ $module->stages->count() }} مرحله استاندارد</p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.panel>
    @endforeach

    @if ($projects->isEmpty())
        <x-ui.empty-state title="هیچ پروژه‌ای ثبت نشده است.">
        </x-ui.empty-state>
    @endif
</div>
@endsection
