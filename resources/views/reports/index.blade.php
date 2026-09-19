@extends('layouts.app')

@section('title', 'گزارش‌های سیستم')
@section('header_title', 'گزارش‌های سیستم')

@section('content')
<div class="max-w-6xl mx-auto space-y-6">

    <!-- KPI Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm flex items-center gap-4">
            <div class="bg-blue-100 p-3 rounded-lg text-blue-600">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            </div>
            <div>
                <p class="text-sm font-medium text-slate-500">وزن کل تسک‌ها</p>
                <p class="text-2xl font-bold text-slate-800">{{ number_format($totalWeight, 1) }}</p>
            </div>
        </div>
        
        <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm flex items-center gap-4">
            <div class="bg-green-100 p-3 rounded-lg text-green-600">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
                <p class="text-sm font-medium text-slate-500">وزن تایید نهایی شده (آماده پرداخت)</p>
                <p class="text-2xl font-bold text-slate-800">{{ number_format($approvedWeight, 1) }}</p>
            </div>
        </div>

        <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm flex items-center gap-4">
            <div class="bg-red-100 p-3 rounded-lg text-red-600">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
                <p class="text-sm font-medium text-slate-500">وظایف دارای تاخیر بحرانی</p>
                <p class="text-2xl font-bold text-red-600">{{ $delayedTasks }}</p>
            </div>
        </div>
    </div>

    <!-- Export Actions -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 bg-slate-50">
            <h2 class="text-lg font-bold text-slate-800">برون‌ریزی داده‌ها (CSV Export)</h2>
        </div>
        
        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="border border-slate-200 rounded-xl p-5 hover:border-blue-300 transition-colors">
                <h3 class="font-bold text-slate-800 text-lg mb-2">گزارش آمادگی پرداخت</h3>
                <p class="text-slate-500 text-sm mb-4">فهرست تمامی وظایف تایید نهایی شده (Approved) همراه با جزئیات پروژه و وزن تخصیص یافته.</p>
                <a href="{{ route('reports.export', ['type' => 'approved_tasks']) }}" class="inline-flex items-center gap-2 px-4 py-2 bg-slate-100 text-slate-700 rounded-lg hover:bg-slate-200 font-medium transition">
                    <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    دانلود CSV
                </a>
            </div>

            <div class="border border-slate-200 rounded-xl p-5 hover:border-blue-300 transition-colors">
                <h3 class="font-bold text-slate-800 text-lg mb-2">گزارش وضعیت و تاخیرات SLA</h3>
                <p class="text-slate-500 text-sm mb-4">گزارش جامع از تمامی وظایف سیستم به همراه وضعیت فعلی و بررسی تاخیر از موعد برنامه‌ریزی شده.</p>
                <a href="{{ route('reports.export', ['type' => 'all_tasks']) }}" class="inline-flex items-center gap-2 px-4 py-2 bg-slate-100 text-slate-700 rounded-lg hover:bg-slate-200 font-medium transition">
                    <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    دانلود CSV
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
