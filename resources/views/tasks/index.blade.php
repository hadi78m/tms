@extends('layouts.app')

@section('title', 'مدیریت وظایف')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-slate-800">فهرست وظایف</h1>
        <div class="flex gap-3">
            <input type="text" placeholder="جستجو..." class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            <button class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                فیلتر
            </button>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-right text-sm text-slate-600">
                <thead class="bg-slate-50 text-slate-700 border-b border-slate-200">
                    <tr>
                        <th class="px-6 py-4 font-semibold">شناسه</th>
                        <th class="px-6 py-4 font-semibold">عنوان وظیفه</th>
                        <th class="px-6 py-4 font-semibold">پروژه</th>
                        <th class="px-6 py-4 font-semibold">پیمانکار</th>
                        <th class="px-6 py-4 font-semibold">وزن</th>
                        <th class="px-6 py-4 font-semibold">مهلت انجام</th>
                        <th class="px-6 py-4 font-semibold">وضعیت</th>
                        <th class="px-6 py-4 font-semibold">عملیات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($tasks as $task)
                    <tr class="hover:bg-slate-50 transition">
                        <td class="px-6 py-4">{{ $task->id }}</td>
                        <td class="px-6 py-4 font-medium text-slate-800">{{ $task->title }}</td>
                        <td class="px-6 py-4">{{ $task->project->name ?? '-' }}</td>
                        <td class="px-6 py-4">{{ $task->contractor->name ?? '-' }}</td>
                        <td class="px-6 py-4">{{ $task->weight }}٪</td>
                        <td class="px-6 py-4 text-xs font-mono" dir="ltr">
                            {{ $task->planned_due_at ? jdate($task->planned_due_at)->format('Y/m/d') : '-' }}
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                @if($task->status === 'active') bg-blue-100 text-blue-800
                                @elseif($task->status === 'completed') bg-green-100 text-green-800
                                @else bg-slate-100 text-slate-800 @endif">
                                {{ __('status.' . $task->status) ?? $task->status }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <a href="{{ route('tasks.show', $task->id) }}" class="text-blue-600 hover:text-blue-800 font-medium text-sm">
                                مشاهده جزئیات
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-6 py-8 text-center text-slate-500">
                            هیچ وظیفه‌ای یافت نشد.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($tasks->hasPages())
        <div class="px-6 py-4 border-t border-slate-200">
            {{ $tasks->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
