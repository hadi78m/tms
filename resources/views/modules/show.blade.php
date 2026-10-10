@extends('layouts.app')

@section('title', 'ساختار ماژول پروژه - TMS')
@section('header_title', 'ساختار ماژول پروژه')

@section('content')
{{-- Phase 5B-6 — Visual Migration (مدیریت ساختار ماژول پروژه).
     فقط زبان بصری منتقل شده است: فرم تعریف ماژول‌ها (modules[i][name|code|weight]
     + project_id مخفی)، فرم ویرایش ماژول (name/code/description) و فرم بازتوازن
     وزن (weights[module_id]) عیناً با همان route/فیلدها/مقدارها/محدودیت‌ها حفظ
     شده‌اند؛ محاسبهٔ مجموع وزن و آستانهٔ ۱۰۰٪ بدون تغییر مانده است. --}}
<div class="mx-auto max-w-6xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold text-ink">{{ $project->name }}</h1>
        <a href="{{ route('modules.index') }}"
           class="rounded-ui text-sm font-bold text-primary transition hover:text-primary-hover focus:outline-none focus-visible:ring-2 focus-visible:ring-primary">
            بازگشت به فهرست
        </a>
    </div>

    @php $activeModules = $project->activeModules; @endphp

    @if ($activeModules->isEmpty())
        <x-ui.panel body-class="p-5 sm:p-6">
            <x-slot:header>
                <header class="border-b border-line-subtle px-5 py-3.5 sm:px-6">
                    <h2 class="text-[15px] font-extrabold leading-tight text-ink">تعریف ساختار ماژول</h2>
                    <p class="mt-1 text-xs text-ink-muted">
                        هر ماژول با مجموع وزن فعال ماژول‌ها برابر ۱۰۰٪ و نُه مرحله استاندارد وزن‌دار ایجاد می‌شود.
                    </p>
                </header>
            </x-slot:header>

            <form method="POST" action="{{ route('modules.store') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="project_id" value="{{ $project->id }}">
                @foreach (['A', 'B', 'C'] as $i => $letter)
                    <div class="grid grid-cols-1 items-end gap-3 md:grid-cols-3">
                        <div>
                            <label for="module-{{ $i }}-name" class="mb-1 block text-sm font-bold text-ink">نام ماژول {{ $i + 1 }}</label>
                            <input id="module-{{ $i }}-name" type="text" name="modules[{{ $i }}][name]"
                                   class="w-full rounded-ui border border-line-strong bg-surface-base px-3 py-2 text-sm text-ink focus:border-primary focus:ring-2 focus:ring-primary/30"
                                   placeholder="ماژول {{ $letter }}">
                        </div>
                        <div>
                            <label for="module-{{ $i }}-code" class="mb-1 block text-sm font-bold text-ink">کد (اختیاری)</label>
                            <input id="module-{{ $i }}-code" type="text" name="modules[{{ $i }}][code]" maxlength="50"
                                   class="w-full rounded-ui border border-line-strong bg-surface-base px-3 py-2 text-sm text-ink focus:border-primary focus:ring-2 focus:ring-primary/30">
                        </div>
                        <div>
                            <label for="module-{{ $i }}-weight" class="mb-1 block text-sm font-bold text-ink">وزن (٪)</label>
                            <input id="module-{{ $i }}-weight" type="number" name="modules[{{ $i }}][weight]" step="0.01" min="0.01" max="100"
                                   class="w-full rounded-ui border border-line-strong bg-surface-base px-3 py-2 text-sm text-ink focus:border-primary focus:ring-2 focus:ring-primary/30"
                                   placeholder="مثلاً 40">
                        </div>
                    </div>
                @endforeach
                <div class="flex justify-end">
                    <button type="submit"
                            class="rounded-ui bg-primary px-5 py-2 font-bold text-white shadow-ui-soft transition hover:bg-primary-hover focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                        تعریف ماژول‌ها
                    </button>
                </div>
            </form>
        </x-ui.panel>
    @else
        @php $activeWeightSum = $activeModules->sum('weight'); @endphp

        <x-ui.panel body-class="p-5 sm:p-6">
            <x-slot:header>
                <header class="flex flex-wrap items-center justify-between gap-3 border-b border-line-subtle px-5 py-3.5 sm:px-6">
                    <h2 class="text-[15px] font-extrabold leading-tight text-ink">ماژول‌های فعال</h2>
                    <span class="rounded-full px-3 py-1 text-sm font-medium {{ abs($activeWeightSum - 100) < 0.01 ? 'bg-success-50 text-success-700' : 'bg-danger-50 text-danger-700' }}">
                        مجموع وزن: {{ number_format($activeWeightSum, 2) }}٪ از ۱۰۰٪
                    </span>
                </header>
            </x-slot:header>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                @foreach ($activeModules as $module)
                    <div class="rounded-ui border border-line-subtle bg-surface-base p-4">
                        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                            <span class="font-bold text-ink">{{ $module->name }}</span>
                            <span class="rounded-full bg-info-100 px-2 py-0.5 text-sm text-info-700">{{ number_format($module->weight, 2) }}%</span>
                        </div>
                        <dl class="mb-3 space-y-1 text-xs text-ink-muted">
                            <div>کد: {{ $module->code ?? '-' }}</div>
                            <div>مراحل: {{ $module->stages->count() }}</div>
                        </dl>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($module->stages as $stage)
                                <a href="{{ route('modules.stages.show', $stage->id) }}"
                                   class="rounded-ui bg-surface-inset px-2 py-1 text-xs text-ink-muted transition hover:bg-line hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-primary">
                                    {{ $stage->name }} ({{ number_format($stage->weight, 1) }}٪)
                                </a>
                            @endforeach
                        </div>
                        <details class="mt-3">
                            <summary class="cursor-pointer text-xs font-bold text-primary transition hover:text-primary-hover">ویرایش اطلاعات</summary>
                            <form method="POST" action="{{ route('modules.update', $module->id) }}" class="mt-2 space-y-2">
                                @csrf
                                <input id="module-{{ $module->id }}-edit-name" type="text" name="name" value="{{ $module->name }}" required
                                       class="w-full rounded-ui border border-line-strong bg-surface-base px-3 py-2 text-sm text-ink focus:border-primary focus:ring-2 focus:ring-primary/30"
                                       placeholder="نام ماژول">
                                <input id="module-{{ $module->id }}-edit-code" type="text" name="code" value="{{ $module->code }}" maxlength="50"
                                       class="w-full rounded-ui border border-line-strong bg-surface-base px-3 py-2 text-sm text-ink focus:border-primary focus:ring-2 focus:ring-primary/30"
                                       placeholder="کد">
                                <input id="module-{{ $module->id }}-edit-description" type="text" name="description" value="{{ $module->description }}"
                                       class="w-full rounded-ui border border-line-strong bg-surface-base px-3 py-2 text-sm text-ink focus:border-primary focus:ring-2 focus:ring-primary/30"
                                       placeholder="توضیحات">
                                <button type="submit"
                                        class="w-full rounded-ui border border-line-strong bg-surface-base px-3 py-1.5 text-xs font-bold text-ink transition hover:bg-surface-inset focus:outline-none focus-visible:ring-2 focus-visible:ring-primary">
                                    ذخیره
                                </button>
                            </form>
                        </details>
                    </div>
                @endforeach
            </div>
        </x-ui.panel>

        <x-ui.panel body-class="p-5 sm:p-6">
            <x-slot:header>
                <header class="border-b border-line-subtle px-5 py-3.5 sm:px-6">
                    <h2 class="text-[15px] font-extrabold leading-tight text-ink">بازتوازن وزن ماژول‌ها</h2>
                    <p class="mt-1 text-xs text-ink-muted">وزن هر ماژول فقط از این مسیر تغییر می‌کند؛ مجموع باید دقیقاً ۱۰۰٪ شود.</p>
                </header>
            </x-slot:header>

            <form method="POST" action="{{ route('modules.rebalance', $project->id) }}" class="space-y-3">
                @csrf
                @foreach ($activeModules as $module)
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="w-40 text-sm text-ink">{{ $module->name }}</span>
                        <input type="number" name="weights[{{ $module->id }}]" value="{{ $module->weight }}" step="0.01" min="0.01" max="100"
                               aria-label="وزن ماژول {{ $module->name }} (درصد)"
                               class="w-40 rounded-ui border border-line-strong bg-surface-base px-3 py-2 text-sm text-ink focus:border-primary focus:ring-2 focus:ring-primary/30" required>
                        <span class="text-sm text-ink-subtle">٪</span>
                    </div>
                @endforeach
                <div class="flex justify-end">
                    <button type="submit"
                            class="rounded-ui bg-primary px-5 py-2 font-bold text-white shadow-ui-soft transition hover:bg-primary-hover focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                        اعمال توزیع جدید
                    </button>
                </div>
            </form>
        </x-ui.panel>
    @endif
</div>
@endsection
