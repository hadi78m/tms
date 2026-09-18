@extends('layouts.app')

@section('title', 'داشبورد')
@section('header_title', 'داشبورد کاربری')

@section('content')
<div class="space-y-6">

    <!-- Welcome Section -->
    <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
        <h2 class="text-xl font-bold text-slate-800 mb-2">خوش آمدید، {{ auth()->user()->name }}!</h2>
        <p class="text-slate-600">در اینجا خلاصه‌ای از وضعیت وظایف شما نمایش داده شده است.</p>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        
        <!-- My Tasks Card -->
        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200 flex items-center justify-between hover:shadow-md transition-shadow">
            <div>
                <p class="text-sm font-medium text-slate-500 uppercase tracking-wider mb-1">
                    {{ auth()->user()->contractor_id ? 'وظایف من' : 'وظایف نیازمند بررسی' }}
                </p>
                <h3 class="text-3xl font-bold text-slate-800">{{ $myTasksCount }}</h3>
            </div>
            <div class="w-12 h-12 bg-blue-50 text-blue-600 rounded-full flex items-center justify-center text-xl">
                <i class="fas {{ auth()->user()->contractor_id ? 'fa-tasks' : 'fa-clipboard-check' }}"></i>
            </div>
        </div>

        <!-- Breached SLA Card -->
        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200 flex items-center justify-between hover:shadow-md transition-shadow">
            <div>
                <p class="text-sm font-medium text-slate-500 uppercase tracking-wider mb-1">نقض زمانبندی (SLA)</p>
                <h3 class="text-3xl font-bold text-red-600">{{ $breachedSlapCount }}</h3>
            </div>
            <div class="w-12 h-12 bg-red-50 text-red-600 rounded-full flex items-center justify-center text-xl">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
        </div>

        <!-- Shortcut Card -->
        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200 flex flex-col justify-center items-start hover:shadow-md transition-shadow group">
            <p class="text-sm font-medium text-slate-500 uppercase tracking-wider mb-2">دسترسی سریع</p>
            <a href="{{ route('tasks.index') }}" class="text-blue-600 font-semibold group-hover:text-blue-800 flex items-center">
                برو به کارتابل وظایف
                <i class="fas fa-arrow-left mr-2 transform group-hover:-translate-x-1 transition-transform"></i>
            </a>
        </div>

    </div>

    <!-- Status Distribution -->
    <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
        <h3 class="text-lg font-bold text-slate-800 mb-4">توزیع وضعیت وظایف</h3>
        @if(empty($statusDistribution))
            <p class="text-slate-500 text-sm">هیچ وظیفه‌ای برای نمایش وجود ندارد.</p>
        @else
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                @php
                    $statusTranslations = [
                        'pending' => 'در انتظار',
                        'in_progress' => 'در حال انجام',
                        'completed' => 'ارسال شده',
                        'approved' => 'تایید شده',
                        'rejected' => 'رد شده',
                    ];
                    $statusColors = [
                        'pending' => 'bg-slate-100 text-slate-700',
                        'in_progress' => 'bg-blue-100 text-blue-700',
                        'completed' => 'bg-purple-100 text-purple-700',
                        'approved' => 'bg-green-100 text-green-700',
                        'rejected' => 'bg-red-100 text-red-700',
                    ];
                @endphp
                @foreach($statusDistribution as $status => $count)
                    <div class="flex items-center justify-between p-3 rounded-lg {{ $statusColors[$status] ?? 'bg-gray-100 text-gray-700' }}">
                        <span class="text-sm font-medium">{{ $statusTranslations[$status] ?? $status }}</span>
                        <span class="text-lg font-bold">{{ $count }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

</div>
@endsection
