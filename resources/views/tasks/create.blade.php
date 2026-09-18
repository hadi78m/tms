@extends('layouts.app')

@section('title', 'ایجاد وظیفه جدید')
@section('header_title', 'ایجاد وظیفه جدید')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 bg-slate-50">
            <h2 class="text-lg font-bold text-slate-800">اطلاعات وظیفه</h2>
        </div>

        <form action="{{ route('tasks.store') }}" method="POST" enctype="multipart/form-data" class="p-6 space-y-6">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- عنوان وظیفه -->
                <div class="md:col-span-2">
                    <label for="title" class="block text-sm font-medium text-slate-700 mb-1">عنوان وظیفه <span class="text-red-500">*</span></label>
                    <input type="text" name="title" id="title" value="{{ old('title') }}" required
                           class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                </div>

                <!-- پروژه -->
                <div>
                    <label for="project_id" class="block text-sm font-medium text-slate-700 mb-1">پروژه مرتبط <span class="text-red-500">*</span></label>
                    <select name="project_id" id="project_id" required
                            class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors bg-white">
                        <option value="">انتخاب پروژه...</option>
                        @foreach($projects as $project)
                            <option value="{{ $project->id }}" {{ old('project_id') == $project->id ? 'selected' : '' }}>
                                {{ $project->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- فاز WBS -->
                <div>
                    <label for="wbs_phase_id" class="block text-sm font-medium text-slate-700 mb-1">فاز WBS</label>
                    <select name="wbs_phase_id" id="wbs_phase_id"
                            class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors bg-white">
                        <option value="">بدون فاز...</option>
                        @foreach($wbsPhases as $phase)
                            <option value="{{ $phase->id }}" {{ old('wbs_phase_id') == $phase->id ? 'selected' : '' }}>
                                {{ $phase->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- اولویت -->
                <div>
                    <label for="priority" class="block text-sm font-medium text-slate-700 mb-1">اولویت <span class="text-red-500">*</span></label>
                    <select name="priority" id="priority" required
                            class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors bg-white">
                        <option value="low" {{ old('priority') == 'low' ? 'selected' : '' }}>پایین</option>
                        <option value="medium" {{ old('priority', 'medium') == 'medium' ? 'selected' : '' }}>متوسط</option>
                        <option value="high" {{ old('priority') == 'high' ? 'selected' : '' }}>بالا</option>
                        <option value="critical" {{ old('priority') == 'critical' ? 'selected' : '' }}>بحرانی</option>
                    </select>
                </div>

                <!-- وزن -->
                <div>
                    <label for="weight" class="block text-sm font-medium text-slate-700 mb-1">وزن (۰ تا ۱۰۰) <span class="text-red-500">*</span></label>
                    <input type="number" step="0.1" min="0" max="100" name="weight" id="weight" value="{{ old('weight', 0) }}" required
                           class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" dir="ltr">
                </div>

                <!-- تاریخ شروع -->
                <div>
                    <label for="planned_start_date" class="block text-sm font-medium text-slate-700 mb-1">تاریخ شروع (میلادی)</label>
                    <input type="date" name="planned_start_date" id="planned_start_date" value="{{ old('planned_start_date') }}"
                           class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" dir="ltr">
                </div>

                <!-- تاریخ پایان -->
                <div>
                    <label for="planned_due_date" class="block text-sm font-medium text-slate-700 mb-1">مهلت انجام (میلادی)</label>
                    <input type="date" name="planned_due_date" id="planned_due_date" value="{{ old('planned_due_date') }}"
                           class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" dir="ltr">
                </div>

                <!-- پیوست اولیه -->
                <div class="md:col-span-2">
                    <label for="document" class="block text-sm font-medium text-slate-700 mb-1">پیوست اولیه (اختیاری)</label>
                    <input type="file" name="document" id="document"
                           class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors text-sm file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    <p class="mt-1 text-xs text-slate-500">حداکثر حجم فایل: ۲۰ مگابایت</p>
                </div>

                <!-- توضیحات -->
                <div class="md:col-span-2">
                    <label for="description" class="block text-sm font-medium text-slate-700 mb-1">توضیحات وظیفه</label>
                    <textarea name="description" id="description" rows="4"
                              class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">{{ old('description') }}</textarea>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-6 border-t border-slate-200">
                <a href="{{ route('tasks.index') }}" class="px-5 py-2 text-slate-600 bg-slate-100 rounded-lg hover:bg-slate-200 transition-colors font-medium">
                    انصراف
                </a>
                <button type="submit" class="px-5 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow-sm transition-all font-medium flex items-center gap-2">
                    <i class="fas fa-save"></i>
                    ثبت وظیفه
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
