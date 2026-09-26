@extends('layouts.app')

@section('title', 'ساختار ماژول پروژه - TMS')
@section('header_title', 'ساختار ماژول پروژه')

@section('content')
<div class="max-w-6xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-slate-800">{{ $project->name }}</h1>
        <a href="{{ route('modules.index') }}" class="text-sm text-blue-600 hover:underline">بازگشت به فهرست</a>
    </div>

    @php $activeModules = $project->activeModules; @endphp

    @if ($activeModules->isEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
            <h2 class="text-lg font-bold text-slate-800 mb-2">تعریف ساختار ماژول</h2>
            <p class="text-sm text-slate-500 mb-4">
                هر ماژول با مجموع وزن فعال ماژول‌ها برابر ۱۰۰٪ و نُه مرحله استاندارد وزن‌دار ایجاد می‌شود.
            </p>
            <form method="POST" action="{{ route('modules.store') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="project_id" value="{{ $project->id }}">
                @foreach (['A', 'B', 'C'] as $i => $letter)
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">نام ماژول {{ $i + 1 }}</label>
                            <input type="text" name="modules[{{ $i }}][name]" class="w-full rounded-lg border-slate-300" placeholder="ماژول {{ $letter }}">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">کد (اختیاری)</label>
                            <input type="text" name="modules[{{ $i }}][code]" class="w-full rounded-lg border-slate-300" maxlength="50">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">وزن (٪)</label>
                            <input type="number" name="modules[{{ $i }}][weight]" step="0.01" min="0.01" max="100"
                                   class="w-full rounded-lg border-slate-300" placeholder="مثلاً 40">
                        </div>
                    </div>
                @endforeach
                <div class="text-left">
                    <button type="submit" class="px-5 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium transition">
                        تعریف ماژول‌ها
                    </button>
                </div>
            </form>
        </div>
    @else
        @php $activeWeightSum = $activeModules->sum('weight'); @endphp

        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200 bg-slate-50 flex items-center justify-between">
                <h2 class="text-lg font-bold text-slate-800">ماژول‌های فعال</h2>
                <span class="text-sm {{ abs($activeWeightSum - 100) < 0.01 ? 'text-green-700 bg-green-50' : 'text-red-700 bg-red-50' }} rounded-full px-3 py-1 font-medium">
                    مجموع وزن: {{ number_format($activeWeightSum, 2) }}٪ از ۱۰۰٪
                </span>
            </div>

            <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                @foreach ($activeModules as $module)
                    <div class="border border-slate-200 rounded-lg p-4">
                        <div class="flex items-center justify-between mb-3">
                            <span class="font-bold text-slate-800">{{ $module->name }}</span>
                            <span class="text-sm bg-blue-100 text-blue-700 rounded-full px-2 py-0.5">{{ number_format($module->weight, 2) }}%</span>
                        </div>
                        <dl class="text-xs text-slate-500 space-y-1 mb-3">
                            <div>کد: {{ $module->code ?? '-' }}</div>
                            <div>مراحل: {{ $module->stages->count() }}</div>
                        </dl>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($module->stages as $stage)
                                <a href="{{ route('modules.stages.show', $stage->id) }}"
                                   class="text-xs bg-slate-100 hover:bg-slate-200 rounded px-2 py-1 text-slate-700 transition">
                                    {{ $stage->name }} ({{ number_format($stage->weight, 1) }}٪)
                                </a>
                            @endforeach
                        </div>
                        <details class="mt-3">
                            <summary class="text-xs text-blue-600 cursor-pointer">ویرایش اطلاعات</summary>
                            <form method="POST" action="{{ route('modules.update', $module->id) }}" class="mt-2 space-y-2">
                                @csrf
                                <input type="text" name="name" value="{{ $module->name }}" required
                                       class="w-full rounded-lg border-slate-300 text-sm" placeholder="نام ماژول">
                                <input type="text" name="code" value="{{ $module->code }}" maxlength="50"
                                       class="w-full rounded-lg border-slate-300 text-sm" placeholder="کد">
                                <input type="text" name="description" value="{{ $module->description }}"
                                       class="w-full rounded-lg border-slate-300 text-sm" placeholder="توضیحات">
                                <button type="submit" class="w-full px-3 py-1.5 bg-slate-700 text-white rounded-lg text-xs hover:bg-slate-800 transition">ذخیره</button>
                            </form>
                        </details>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200 bg-slate-50">
                <h2 class="text-lg font-bold text-slate-800">بازتوازن وزن ماژول‌ها</h2>
                <p class="text-sm text-slate-500 mt-1">وزن هر ماژول فقط از این مسیر تغییر می‌کند؛ مجموع باید دقیقاً ۱۰۰٪ شود.</p>
            </div>
            <form method="POST" action="{{ route('modules.rebalance', $project->id) }}" class="p-6 space-y-3">
                @csrf
                @foreach ($activeModules as $module)
                    <div class="flex items-center gap-3">
                        <span class="text-sm text-slate-700 w-40">{{ $module->name }}</span>
                        <input type="number" name="weights[{{ $module->id }}]" value="{{ $module->weight }}" step="0.01" min="0.01" max="100"
                               class="rounded-lg border-slate-300" required>
                        <span class="text-sm text-slate-400">٪</span>
                    </div>
                @endforeach
                <div class="text-left">
                    <button type="submit" class="px-5 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium transition">
                        اعمال توزیع جدید
                    </button>
                </div>
            </form>
        </div>
    @endif
</div>
@endsection
