@extends('layouts.app')

@use('App\Support\TaskBoard\BoardColumns')

@section('title', 'مرحله: ' . $stage->name . ' - TMS')
@section('header_title', 'مرحله: ' . $stage->name)

@section('content')
{{-- Phase 5B-6 — Visual Migration (نمای مرحله ماژول).
     فقط زبان بصری منتقل شده است: فرم‌های بازتوازن وزن، ثبت پیشنهاد، اصلاح و
     تصمیم تأیید پیشرفت (route/CSRF/فیلدها/hiddenها/مقدارها/min-max/required)
     و شرط‌های قفل وزن دست‌نخورده‌اند. نشان‌های وضعیت تأیید با همان برچسب
     قبلی به variantهای design-system ترجمه شده‌اند و وضعیت وظایف از نگاشت
     مرجع BoardColumns خوانده می‌شود. وزن/درصد همان مقادیر کنترلر است. --}}
<div class="mx-auto max-w-5xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-ink">{{ $stage->name }}</h1>
            <p class="mt-1 text-sm text-ink-muted">
                ماژول {{ $module->name }} · پروژه {{ $module->project->name ?? '-' }}
            </p>
        </div>
        <a href="{{ route('modules.show', $module->project_id) }}"
           class="rounded-ui text-sm font-bold text-primary transition hover:text-primary-hover focus:outline-none focus-visible:ring-2 focus-visible:ring-primary">
            بازگشت به ساختار ماژول
        </a>
    </div>

    {{-- Stage weight snapshot — مقادیر عیناً از کنترلر می‌آیند (بدون بازمحاسبه). --}}
    <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
        <x-ui.key-val label="وزن تخصیص‌یافته:">
            {{ number_format($stage->weight, 2) }}٪
        </x-ui.key-val>
        <x-ui.key-val label="مبلغ تأییدشدهٔ مراحل:">
            <span class="text-success-700">{{ number_format($approvedWeight, 2) }}٪</span>
        </x-ui.key-val>
        <x-ui.key-val label="باقی‌مانده:">
            {{ number_format($remainingWeight, 2) }}٪
        </x-ui.key-val>
        <x-ui.key-val label="وضعیت وزن:">
            @if ($isWeightLocked)
                <span class="text-warning-700">قفل‌شده (پس از اولین ثبت)</span>
            @else
                <span class="text-success-700">قابل ویرایش</span>
            @endif
        </x-ui.key-val>
    </div>

    {{-- Weight rebalance --}}
    <x-ui.panel body-class="p-5 sm:p-6">
        <x-slot:header>
            <header class="border-b border-line-subtle px-5 py-3.5 sm:px-6">
                <h2 class="text-[15px] font-extrabold leading-tight text-ink">توزیع وزن مراحل ماژول</h2>
                <p class="mt-1 text-xs text-ink-muted">
                    مجموع وزن نُه مرحله باید ۱۰۰٪ بماند. وزن مرحله‌ای که حتی یک رکورد تأیید پیشرفت دارد، قفل است.
                </p>
            </header>
        </x-slot:header>

        <form method="POST" action="{{ route('modules.stages.rebalance', $module->id) }}" class="space-y-3">
            @csrf
            @php $anyLocked = false; @endphp
            @foreach ($module->stages as $s)
                @php
                    $locked = $s->isWeightLocked();
                    $anyLocked = $anyLocked || $locked;
                @endphp
                <div class="flex flex-wrap items-center gap-3">
                    <span class="w-48 text-sm {{ $locked ? 'font-bold text-warning-700' : 'text-ink' }}">
                        {{ $s->name }} @if($locked) <span title="قفل‌شده" aria-label="قفل‌شده">🔒</span> @endif
                    </span>
                    <input type="number" name="weights[{{ $s->id }}]" value="{{ $s->weight }}" step="0.01" min="0.01" max="100"
                           @if($locked) disabled @endif
                           aria-label="وزن مرحله {{ $s->name }} (درصد)"
                           class="w-40 rounded-ui border border-line-strong px-3 py-2 text-sm text-ink focus:border-primary focus:ring-2 focus:ring-primary/30 {{ $locked ? 'bg-surface-inset text-ink-muted' : 'bg-surface-base' }}"
                           required>
                    <span class="text-sm text-ink-subtle">٪</span>
                </div>
            @endforeach
            <div class="flex flex-col items-end gap-2">
                <button type="submit"
                        class="rounded-ui bg-primary px-5 py-2 font-bold text-white shadow-ui-soft transition hover:bg-primary-hover focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                    اعمال توزیع جدید
                </button>
                @if ($anyLocked)
                    <p class="text-xs text-warning-700">مراحل قفل‌شده از به‌روزرسانی مستثنی هستند.</p>
                @endif
            </div>
        </form>
    </x-ui.panel>

    {{-- Proposal form --}}
    <x-ui.panel body-class="p-5 sm:p-6">
        <x-slot:header>
            <header class="border-b border-line-subtle px-5 py-3.5 sm:px-6">
                <h2 class="text-[15px] font-extrabold leading-tight text-ink">ثبت پیشنهاد پیشرفت مرحله</h2>
            </header>
        </x-slot:header>

        <form method="POST" action="{{ route('stages.progress-approvals.store', $stage->id) }}" class="grid grid-cols-1 items-end gap-4 md:grid-cols-3">
            @csrf
            <div>
                <label for="stage-proposed-amount" class="mb-1 block text-sm font-bold text-ink">مبلغ پیشنهادی (٪ وزن مرحله)</label>
                <input id="stage-proposed-amount" type="number" name="proposed_amount" step="0.01" min="0.01" max="{{ $stage->weight }}"
                       class="w-full rounded-ui border border-line-strong bg-surface-base px-3 py-2 text-sm text-ink focus:border-primary focus:ring-2 focus:ring-primary/30" required>
            </div>
            <div>
                <label for="stage-proposal-reason" class="mb-1 block text-sm font-bold text-ink">دلیل / توضیح (اختیاری)</label>
                <input id="stage-proposal-reason" type="text" name="reason" maxlength="1000"
                       class="w-full rounded-ui border border-line-strong bg-surface-base px-3 py-2 text-sm text-ink focus:border-primary focus:ring-2 focus:ring-primary/30">
            </div>
            <div class="flex justify-end">
                <button type="submit"
                        class="rounded-ui bg-success-600 px-5 py-2 font-bold text-white shadow-ui-soft transition hover:bg-success-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-success-500 focus-visible:ring-offset-2">
                    ثبت پیشنهاد
                </button>
            </div>
        </form>
    </x-ui.panel>

    {{-- Approval history --}}
    <x-ui.panel body-class="p-0">
        <x-slot:header>
            <div class="border-b border-line-subtle px-5 py-3.5 text-[15px] font-extrabold text-ink sm:px-6">
                تاریخچه تأییدهای پیشرفت
            </div>
        </x-slot:header>

        @if ($stage->approvals->isEmpty())
            <div class="p-5 sm:p-6">
                <x-ui.empty-state title="هنوز هیچ رکورد تأیید پیشرفتی برای این مرحله ثبت نشده است." />
            </div>
        @else
            <ul class="divide-y divide-line-subtle">
                @foreach ($stage->approvals->sortByDesc('id') as $approval)
                    <li class="px-5 py-4 sm:px-6">
                        <div class="flex flex-wrap items-center gap-3 text-sm">
                            <span class="font-bold text-ink">#{{ $approval->id }}</span>

                            @if ($approval->isPending() && ! $approval->isSuperseded())
                                <x-ui.badge variant="warning">در انتظار تصمیم</x-ui.badge>
                            @elseif ($approval->status === 'approved')
                                <x-ui.badge variant="success">تأییدشده</x-ui.badge>
                            @elseif ($approval->status === 'rejected')
                                <x-ui.badge variant="danger">ردشده</x-ui.badge>
                            @endif

                            @if ($approval->isSuperseded())
                                <x-ui.badge variant="muted">باطل‌شده (تاریخچه)</x-ui.badge>
                            @endif

                            <span class="text-ink-muted">پیشنهاد: {{ number_format($approval->proposed_amount, 2) }}٪</span>

                            @if ($approval->status === 'approved')
                                <span class="font-medium text-success-700">مبلغ تأییدشده: {{ number_format($approval->approved_amount, 2) }}٪</span>
                            @endif

                            @if ($approval->supersedes_approval_id)
                                <span class="text-xs text-ink-subtle">اصلاحِ رکورد #{{ $approval->supersedes_approval_id }}</span>
                            @endif
                        </div>

                        @if ($approval->reason)
                            <p class="mt-1 text-xs text-ink-muted">دلیل: {{ $approval->reason }}</p>
                        @endif

                        {{-- Available actions per state — ساختار/details/فیلدها عیناً حفظ شده‌اند. --}}
                        @if ($approval->isPending() && ! $approval->isSuperseded())
                            <details class="mt-2">
                                <summary class="cursor-pointer text-xs font-bold text-primary transition hover:text-primary-hover">اقدام</summary>
                                <div class="mt-2 space-y-3">
                                    <form method="POST" action="{{ route('stages.progress-approvals.adjust', $approval->id) }}"
                                          class="flex flex-wrap items-center gap-2">
                                        @csrf
                                        <input type="number" name="proposed_amount" value="{{ $approval->proposed_amount }}"
                                               step="0.01" min="0.01" max="100"
                                               class="w-32 rounded-ui border border-line-strong bg-surface-base px-2.5 py-1.5 text-sm text-ink focus:border-primary focus:ring-2 focus:ring-primary/30" required>
                                        <input type="text" name="reason" placeholder="دلیل اصلاح" maxlength="1000"
                                               class="w-52 rounded-ui border border-line-strong bg-surface-base px-2.5 py-1.5 text-sm text-ink focus:border-primary focus:ring-2 focus:ring-primary/30">
                                        <button type="submit"
                                                class="rounded-ui border border-line-strong bg-surface-base px-3 py-1.5 text-xs font-bold text-ink transition hover:bg-surface-inset focus:outline-none focus-visible:ring-2 focus-visible:ring-primary">
                                            اصلاح مبلغ پیشنهادی
                                        </button>
                                    </form>

                                    <form method="POST" action="{{ route('stages.progress-approvals.decide', $approval->id) }}"
                                          class="flex flex-wrap items-center gap-2">
                                        @csrf
                                        <input type="hidden" name="decision" value="approve">
                                        <label for="approval-{{ $approval->id }}-approved-amount" class="text-xs text-ink-muted">مبلغ تأیید (≤ پیشنهاد):</label>
                                        <input id="approval-{{ $approval->id }}-approved-amount" type="number" name="approved_amount" step="0.01" min="0" max="{{ $approval->proposed_amount }}"
                                               value="{{ $approval->proposed_amount }}"
                                               class="w-32 rounded-ui border border-line-strong bg-surface-base px-2.5 py-1.5 text-sm text-ink focus:border-primary focus:ring-2 focus:ring-primary/30" required>
                                        <input type="text" name="reason" placeholder="توضیح (اختیاری)" maxlength="1000"
                                               class="w-52 rounded-ui border border-line-strong bg-surface-base px-2.5 py-1.5 text-sm text-ink focus:border-primary focus:ring-2 focus:ring-primary/30">
                                        <button type="submit"
                                                class="rounded-ui bg-success-600 px-3 py-1.5 text-xs font-bold text-white shadow-ui-soft transition hover:bg-success-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-success-500">
                                            تأیید
                                        </button>
                                    </form>

                                    <form method="POST" action="{{ route('stages.progress-approvals.decide', $approval->id) }}"
                                          class="flex flex-wrap items-center gap-2">
                                        @csrf
                                        <input type="hidden" name="decision" value="reject">
                                        <input type="text" name="reason" placeholder="دلیل رد (الزامی)" maxlength="1000" required
                                               class="w-64 rounded-ui border border-line-strong bg-surface-base px-2.5 py-1.5 text-sm text-ink focus:border-primary focus:ring-2 focus:ring-primary/30">
                                        <button type="submit"
                                                class="rounded-ui bg-danger-600 px-3 py-1.5 text-xs font-bold text-white shadow-ui-soft transition hover:bg-danger-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-danger-500">
                                            رد
                                        </button>
                                    </form>

                                    @if ($approval->status === 'approved' || $approval->status === 'rejected')
                                        <form method="POST" action="{{ route('stages.progress-approvals.store', $stage->id) }}"
                                              class="flex flex-wrap items-center gap-2 border-t border-line-subtle pt-2">
                                            @csrf
                                            <input type="hidden" name="supersedes_approval_id" value="{{ $approval->id }}">
                                            <span class="text-xs text-ink-muted">اصلاح رکورد تصمیم‌گرفته (جایگزینی — نه افزودن):</span>
                                            <input type="number" name="proposed_amount" step="0.01" min="0.01" max="100"
                                                   class="w-32 rounded-ui border border-line-strong bg-surface-base px-2.5 py-1.5 text-sm text-ink focus:border-primary focus:ring-2 focus:ring-primary/30" required>
                                            <input type="text" name="reason" placeholder="دلیل" maxlength="1000"
                                                   class="w-52 rounded-ui border border-line-strong bg-surface-base px-2.5 py-1.5 text-sm text-ink focus:border-primary focus:ring-2 focus:ring-primary/30">
                                            <button type="submit"
                                                    class="rounded-ui bg-warning-500 px-3 py-1.5 text-xs font-bold text-white shadow-ui-soft transition hover:bg-warning-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-warning-500">
                                                ثبت جایگزین
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </details>
                        @elseif ($approval->status === 'approved' || $approval->status === 'rejected')
                            @if (! $approval->isSuperseded())
                                <details class="mt-2">
                                    <summary class="cursor-pointer text-xs font-bold text-warning-700 transition hover:text-warning-800">اصلاح این تصمیم (ثبت جایگزین)</summary>
                                    <form method="POST" action="{{ route('stages.progress-approvals.store', $stage->id) }}"
                                          class="mt-2 flex flex-wrap items-center gap-2">
                                        @csrf
                                        <input type="hidden" name="supersedes_approval_id" value="{{ $approval->id }}">
                                        <input type="number" name="proposed_amount" step="0.01" min="0.01" max="100"
                                               class="w-32 rounded-ui border border-line-strong bg-surface-base px-2.5 py-1.5 text-sm text-ink focus:border-primary focus:ring-2 focus:ring-primary/30" required>
                                        <input type="text" name="reason" placeholder="دلیل اصلاح" maxlength="1000"
                                               class="w-52 rounded-ui border border-line-strong bg-surface-base px-2.5 py-1.5 text-sm text-ink focus:border-primary focus:ring-2 focus:ring-primary/30">
                                        <button type="submit"
                                                class="rounded-ui bg-warning-500 px-3 py-1.5 text-xs font-bold text-white shadow-ui-soft transition hover:bg-warning-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-warning-500">
                                            ثبت جایگزین
                                        </button>
                                    </form>
                                </details>
                            @endif
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.panel>

    {{-- Tasks attached to this stage --}}
    <x-ui.panel body-class="p-0">
        <x-slot:header>
            <div class="border-b border-line-subtle px-5 py-3.5 text-[15px] font-extrabold text-ink sm:px-6">
                وظایف این مرحله
            </div>
        </x-slot:header>

        @if ($stage->tasks->isEmpty())
            <div class="p-5 sm:p-6">
                <x-ui.empty-state title="وظیفه‌ای به این مرحله متصل نیست." />
            </div>
        @else
            <ul class="divide-y divide-line-subtle">
                @foreach ($stage->tasks as $task)
                    <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3 text-sm sm:px-6">
                        <a href="{{ route('tasks.show', $task->id) }}"
                           class="rounded-ui font-bold text-primary transition hover:text-primary-hover focus:outline-none focus-visible:ring-2 focus-visible:ring-primary">
                            {{ $task->title }}
                        </a>
                        @php $statusView = BoardColumns::statusView((string) $task->status); @endphp
                        <x-ui.badge :variant="$statusView['variant']" dot>{{ $statusView['label'] }}</x-ui.badge>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.panel>
</div>
@endsection
