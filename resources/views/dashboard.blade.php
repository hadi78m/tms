@extends('layouts.app')

@use('App\Support\TaskBoard\BoardColumns')

@section('title', 'داشبورد')
@section('header_title', 'داشبورد کاربری')

@section('content')
{{-- Phase 5B-2 — Visual Migration.
     تنها زبان بصری عوض شده است: Panel/Badge/Avatar و توکن‌های طراحی.
     هیچ metric، query، متن، مجوز یا قاعدهٔ کسب‌وکاری تغییر نکرده است. --}}
<div class="space-y-6">

    <!-- Welcome Section -->
    <x-ui.panel>
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="min-w-0">
                <h2 class="text-lg font-extrabold text-ink">خوش آمدید، {{ auth()->user()->name }}!</h2>
                <p class="mt-1 text-sm leading-relaxed text-ink-muted">در اینجا خلاصه‌ای از وضعیت وظایف شما نمایش داده شده است.</p>
            </div>
            <x-ui.avatar :name="auth()->user()->name" size="lg" />
        </div>
    </x-ui.panel>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-3">

        <!-- My Tasks Card -->
        <x-ui.panel body-class="flex items-center justify-between gap-4">
            <div class="min-w-0">
                <p class="text-xs font-bold text-ink-muted">
                    {{ auth()->user()->contractor_id ? 'وظایف من' : 'وظایف نیازمند بررسی' }}
                </p>
                <p class="mt-1 text-3xl font-extrabold tabular-nums text-ink">{{ $myTasksCount }}</p>
            </div>
            <span class="grid h-12 w-12 shrink-0 place-items-center rounded-ui-md bg-primary/10 text-lg text-primary ring-1 ring-primary/20" aria-hidden="true">
                <i class="fas {{ auth()->user()->contractor_id ? 'fa-tasks' : 'fa-clipboard-check' }}"></i>
            </span>
        </x-ui.panel>

        <!-- Breached SLA Card -->
        <x-ui.panel body-class="flex items-center justify-between gap-4">
            <div class="min-w-0">
                <p class="text-xs font-bold text-ink-muted">نقض زمان‌بندی (SLA)</p>
                <p class="mt-1 text-3xl font-extrabold tabular-nums text-danger-600">{{ $breachedSlapCount }}</p>
            </div>
            <span class="grid h-12 w-12 shrink-0 place-items-center rounded-ui-md bg-danger-100 text-lg text-danger-600 ring-1 ring-danger-200" aria-hidden="true">
                <i class="fas fa-exclamation-triangle"></i>
            </span>
        </x-ui.panel>

        <!-- Shortcut Card -->
        <x-ui.panel body-class="flex flex-col items-start justify-center gap-2">
            <p class="text-xs font-bold text-ink-muted">دسترسی سریع</p>
            <a href="{{ route('tasks.index') }}"
               class="group inline-flex items-center gap-1.5 rounded-ui text-sm font-bold text-primary transition hover:text-primary-hover focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                برو به کارتابل وظایف
                <i class="fas fa-arrow-left ms-1 transform transition-transform group-hover:-translate-x-1" aria-hidden="true"></i>
            </a>
        </x-ui.panel>

    </div>

    <!-- Status Distribution -->
    <x-ui.panel title="توزیع وضعیت وظایف">
        {{-- برچسب وضعیت فقط از منبع واحد ارائه (BoardColumns) خوانده می‌شود.
             کلیدهای قدیمی pending/completed/rejected هرگز از وضعیت‌های
             واقعی دامنه نبودند؛ اکنون ۹ وضعیت واقعی با برچسب فارسی
             و رنگ معنایی درست نمایش داده می‌شوند. --}}
        @if(empty($statusDistribution))
            <x-ui.empty-state title="هیچ وظیفه‌ای برای نمایش وجود ندارد." />
        @else
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4">
                @foreach($statusDistribution as $status => $count)
                    @php $statusView = BoardColumns::statusView((string) $status); @endphp
                    <div class="flex items-center justify-between gap-3 rounded-ui border border-line-subtle bg-surface-inset p-3">
                        <x-ui.badge :variant="$statusView['variant']" dot>{{ $statusView['label'] }}</x-ui.badge>
                        <span class="text-lg font-extrabold tabular-nums text-ink">{{ $count }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </x-ui.panel>

</div>
@endsection
