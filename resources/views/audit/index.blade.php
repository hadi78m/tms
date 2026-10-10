@extends('layouts.app')

@section('title', isset($scopedTask) ? 'گزارش فعالیت وظیفه #' . $scopedTask->id : 'گزارش فعالیت (Audit)')
@section('header_title', isset($scopedTask) ? 'گزارش فعالیت وظیفه #' . $scopedTask->id : 'گزارش فعالیت (Audit)')

@section('content')
{{-- Phase 5B-7A — Visual Migration (گزارش فعالیت / Audit).
     فقط زبان بصری به Design System منتقل شده است: Panel/Badge/EmptyState/توکن‌ها.
     فرم GET و فیلدهای action/entity_type، حالت selected، ۷ ستون جدول،
     ماسک DEC-045، ترتیب رکوردها و pagination همگی دست‌نخورده‌اند. --}}
<div class="max-w-6xl mx-auto space-y-6">

    <x-ui.panel body-class="p-0" class="overflow-hidden">
        <x-slot:header>
            <header class="flex flex-wrap items-center justify-between gap-3 border-b border-line-subtle px-5 py-3.5">
                <div>
                    <h2 class="text-lg font-extrabold text-ink">گزارش فعالیت سیستم</h2>
                    <p class="mt-0.5 text-xs text-ink-muted">
                        این گزارش فقط-خواندنی است (immutable). طبق تصمیم DEC-045، مقادیر حساس (IP و User-Agent) ماسک شده‌اند.
                    </p>
                </div>
                <x-ui.badge variant="muted" dot>فقط-خواندنی</x-ui.badge>
            </header>
        </x-slot:header>

        @if(!isset($scopedTask))
        <!-- Filters -->
        <div class="border-b border-line-subtle bg-surface-inset/50 px-5 py-4">
            <form method="GET" action="{{ route('audit.index') }}" class="flex flex-wrap items-end gap-3">
                <div>
                    <label for="audit-action" class="mb-1 block text-xs font-bold text-ink-muted">نوع رویداد</label>
                    <select id="audit-action" name="action" class="rounded-ui border-line bg-surface-base px-3 py-2 text-sm text-ink shadow-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/30">
                        <option value="">همه</option>
                        @foreach($actions as $action)
                            <option value="{{ $action }}" {{ $filters['action'] === $action ? 'selected' : '' }}>{{ $action }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="audit-entity-type" class="mb-1 block text-xs font-bold text-ink-muted">موجودیت</label>
                    <select id="audit-entity-type" name="entity_type" class="rounded-ui border-line bg-surface-base px-3 py-2 text-sm text-ink shadow-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/30">
                        <option value="">همه</option>
                        @foreach($entityTypes as $type)
                            <option value="{{ $type }}" {{ $filters['entity_type'] === $type ? 'selected' : '' }}>{{ $type }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="inline-flex items-center rounded-ui bg-primary px-4 py-2 text-sm font-bold text-white shadow-ui-soft transition hover:bg-primary-hover focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                    اعمال فیلتر
                </button>
                <a href="{{ route('audit.index') }}" class="inline-flex items-center rounded-ui border border-line bg-surface-base px-4 py-2 text-sm font-bold text-ink-muted transition hover:border-line-strong hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-primary">
                    حذف فیلترها
                </a>
            </form>
        </div>
        @endif

        <div class="overflow-x-auto">
            <table class="w-full min-w-[52rem] border-collapse text-right text-sm">
                <caption class="sr-only">جدول گزارش فعالیت سیستم شامل رویداد، موجودیت، کاربر، تغییرات، دلیل و زمان</caption>
                <thead>
                    <tr class="border-b border-line bg-surface-inset text-xs font-bold tracking-wider text-ink-muted">
                        <th scope="col" class="px-4 py-3 font-bold">#</th>
                        <th scope="col" class="px-4 py-3 font-bold">رویداد</th>
                        <th scope="col" class="px-4 py-3 font-bold">موجودیت</th>
                        <th scope="col" class="px-4 py-3 font-bold">کاربر</th>
                        <th scope="col" class="px-4 py-3 font-bold">تغییرات</th>
                        <th scope="col" class="px-4 py-3 font-bold">دلیل</th>
                        <th scope="col" class="px-4 py-3 font-bold">زمان</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line-subtle text-ink">
                    @forelse ($logs as $log)
                        <tr class="transition-colors hover:bg-surface-inset/60">
                            <td class="px-4 py-3 text-ink-subtle">{{ $log->id }}</td>
                            <td class="px-4 py-3">
                                <span dir="ltr" class="inline-block font-mono text-xs text-ink">{{ $log->action }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <span dir="ltr" class="inline-block rounded-ui bg-surface-inset px-2 py-0.5 font-mono text-xs text-ink-muted ring-1 ring-inset ring-line-subtle">
                                    {{ $log->entity_type }}#{{ $log->entity_id }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <x-ui.avatar :name="$log->user?->name ?? '—'" size="xs" />
                                    <span class="text-ink">{{ $log->user?->name ?? '—' }}</span>
                                    @if(isset($log->new_values['actor_role']))
                                        <span class="text-xs text-ink-subtle">({{ $log->new_values['actor_role'] }})</span>
                                    @endif
                                </div>
                            </td>
                            <td class="max-w-xs px-4 py-3 text-xs text-ink-muted" title="@json($log->old_values) → @json($log->new_values)">
                                @if($log->old_values || $log->new_values)
                                    <span dir="ltr" class="block break-words font-mono leading-relaxed">{{ \Illuminate\Support\Str::limit(json_encode($log->new_values, JSON_UNESCAPED_UNICODE), 80) }}</span>
                                @else
                                    <span class="text-ink-subtle">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-ink-muted">{{ $log->reason ?? '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-xs text-ink-subtle" dir="ltr">{{ jdate($log->created_at)->format('Y/m/d H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-6">
                                <x-ui.empty-state title="هیچ رکورد فعالیتی یافت نشد."
                                                  description="با تغییر فیلترهای «نوع رویداد» یا «موجودیت» دوباره تلاش کنید.">
                                </x-ui.empty-state>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($logs->hasPages())
        <div class="border-t border-line-subtle px-5 py-4">
            {{ $logs->links() }}
        </div>
        @endif
    </x-ui.panel>
</div>
@endsection
