@extends('layouts.app')

@use('App\Domain\Enums\TaskPriority')
@use('App\Support\TaskBoard\BoardColumns')

@section('title', 'مدیریت وظایف')
@section('header_title', 'فهرست وظایف')

@section('content')
{{-- Phase 5B-2 — Visual Migration (جدول).
     زبان بصری به Design System منتقل شده است. route/query/filterهای
     سمت سرور/pagination/authorization/project scope و معناشناسی وضعیت و
     اولویت دست‌نخورده‌اند. ساختار جدول حفظ شده تا اطلاعات قابل‌مشاهده و
     قابلیت مقایسهٔ ستون‌ها از دست نرود؛ فقط presentation عوض شده است. --}}
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <h1 class="text-2xl font-bold text-ink">فهرست وظایف</h1>
            <span class="inline-flex items-center rounded-full bg-surface-inset px-2.5 py-1 text-xs font-bold text-ink-muted ring-1 ring-inset ring-line">
                {{ $tasks->total() }} وظیفه
            </span>
        </div>

        {{-- View Switcher (Board Gate §19): Board is a second read-only view
             over the same tasks. The existing table query/behavior is
             untouched — this only adds the navigation control. --}}
        <div class="inline-flex items-center gap-1 rounded-ui-md border border-line bg-surface-inset p-1">
            <span aria-current="true"
                  class="inline-flex items-center gap-1.5 rounded-ui bg-primary px-3.5 py-1.5 text-[13px] font-bold text-white shadow-ui-soft">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
                جدول
            </span>
            <a href="{{ route('tasks.board') }}"
               class="inline-flex items-center gap-1.5 rounded-ui px-3.5 py-1.5 text-[13px] font-bold text-ink-muted transition-colors hover:bg-surface-base hover:text-ink">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 5h3v9h-3zM16 5h3v12h-3zM4 5h3v14H4z" />
                </svg>
                تابلو
            </a>
        </div>
    </div>

    {{-- فیلترهای واقعی سمت سرور — همان معناشناسی صفحهٔ تابلو (tasks.board):
         search روی «عنوان وظیفه»، project روی project_id، status روی وضعیت
         واقعی دامنه (نه گروه نمایشی) و priority روی مقدار enum.
         فرم GET است تا نتایج قابل نشانه‌گذاری و اشتراک‌گذاری باشند. --}}
    <form method="GET" action="{{ route('tasks.index') }}" class="space-y-3" role="search">
        <div class="flex flex-wrap items-end gap-3">
            <div class="grow basis-56">
                <label for="index-search" class="mb-1 block text-xs font-bold text-ink-muted">جستجو</label>
                <div class="relative">
                    <svg class="pointer-events-none absolute inset-y-0 start-3 my-auto h-4 w-4 text-ink-subtle" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <input id="index-search"
                           type="search"
                           name="search"
                           value="{{ $filters['search'] }}"
                           placeholder="جستجو بر اساس عنوان وظیفه…"
                           class="w-full rounded-ui border-line bg-surface-base py-2 ps-9 pe-3 text-sm text-ink shadow-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/30">
                </div>
            </div>

            <div class="basis-44 grow">
                <label for="index-project" class="mb-1 block text-xs font-bold text-ink-muted">پروژه</label>
                <select id="index-project"
                        name="project"
                        class="w-full rounded-ui border-line bg-surface-base py-2 px-3 text-sm text-ink shadow-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/30">
                    <option value="">همه پروژه‌ها</option>
                    @foreach ($projects as $projectId => $projectName)
                        <option value="{{ $projectId }}" {{ (string) $filters['project'] === (string) $projectId ? 'selected' : '' }}>
                            {{ $projectName }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="basis-48 grow">
                <label for="index-status" class="mb-1 block text-xs font-bold text-ink-muted">وضعیت</label>
                <select id="index-status"
                        name="status"
                        class="w-full rounded-ui border-line bg-surface-base py-2 px-3 text-sm text-ink shadow-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/30">
                    <option value="">همه وضعیت‌ها</option>
                    @foreach (BoardColumns::filterableStatuses() as $value)
                        <option value="{{ $value }}" {{ $filters['status'] === $value ? 'selected' : '' }}>
                            {{ BoardColumns::statusLabel($value) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="basis-40 grow">
                <label for="index-priority" class="mb-1 block text-xs font-bold text-ink-muted">اولویت</label>
                <select id="index-priority"
                        name="priority"
                        class="w-full rounded-ui border-line bg-surface-base py-2 px-3 text-sm text-ink shadow-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/30">
                    <option value="">همه اولویت‌ها</option>
                    @foreach (TaskPriority::cases() as $priority)
                        <option value="{{ $priority->value }}" {{ $filters['priority'] === $priority->value ? 'selected' : '' }}>
                            {{ $priority->label() }}
                        </option>
                    @endforeach
                </select>
            </div>

            <button type="submit"
                    class="inline-flex items-center gap-2 rounded-ui bg-primary px-4 py-2 text-sm font-bold text-white shadow-ui-soft transition hover:bg-primary-hover focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                اعمال فیلتر
            </button>

            @if (collect($filters)->filter()->isNotEmpty())
                <a href="{{ route('tasks.index') }}"
                   class="inline-flex items-center rounded-ui border border-line bg-surface-base px-4 py-2 text-sm font-bold text-ink-muted transition hover:border-line-strong hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-primary">
                    حذف فیلترها
                </a>
            @endif
        </div>
    </form>

    <x-ui.panel body-class="p-0">
        <div class="overflow-x-auto">
            <table class="w-full text-right text-sm">
                <thead class="border-b border-line bg-surface-inset text-ink-muted">
                    <tr>
                        <th class="px-6 py-4 font-bold">شناسه</th>
                        <th class="px-6 py-4 font-bold">عنوان وظیفه</th>
                        <th class="px-6 py-4 font-bold">پروژه</th>
                        <th class="px-6 py-4 font-bold">پیمانکار</th>
                        <th class="px-6 py-4 font-bold">وزن</th>
                        <th class="px-6 py-4 font-bold">مهلت انجام</th>
                        <th class="px-6 py-4 font-bold">وضعیت</th>
                        <th class="px-6 py-4 font-bold">عملیات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line-subtle">
                    @forelse($tasks as $task)
                    <tr class="transition-colors hover:bg-surface-inset/60">
                        <td class="px-6 py-4 tabular-nums text-ink-muted">{{ $task->id }}</td>
                        <td class="px-6 py-4 font-bold text-ink">{{ $task->title }}</td>
                        <td class="px-6 py-4 text-ink-muted">{{ $task->project->name ?? '-' }}</td>
                        <td class="px-6 py-4">
                            @if($task->contractor)
                                <span class="flex items-center gap-2 text-ink-muted">
                                    <x-ui.avatar :name="$task->contractor->name" size="xs" />
                                    {{ $task->contractor->name }}
                                </span>
                            @else
                                <span class="text-ink-subtle">-</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 tabular-nums text-ink-muted">{{ $task->weight }}٪</td>
                        <td class="px-6 py-4 text-xs font-mono text-ink-muted" dir="ltr">
                            {{ $task->planned_due_at ? jdate($task->planned_due_at)->format('Y/m/d') : '-' }}
                        </td>
                        <td class="px-6 py-4">
                            {{-- وضعیت فقط از منبع واحد ارائه (BoardColumns) خوانده می‌شود؛
                                 نگاشت تکراری در این ویو وجود ندارد. --}}
                            @php $statusView = BoardColumns::statusView((string) $task->status); @endphp
                            <x-ui.badge :variant="$statusView['variant']" dot>{{ $statusView['label'] }}</x-ui.badge>
                        </td>
                        <td class="px-6 py-4">
                            <a href="{{ route('tasks.show', $task->id) }}"
                               class="rounded-ui font-bold text-primary transition-colors hover:text-primary-hover focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                                مشاهده جزئیات
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="p-4">
                            <x-ui.empty-state title="هیچ وظیفه‌ای یافت نشد." description="با تغییر یا حذف فیلترها می‌توانید نتیجه‌های بیشتری ببینید." />
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($tasks->hasPages())
        <div class="border-t border-line-subtle px-6 py-4">
            {{ $tasks->links() }}
        </div>
        @endif
    </x-ui.panel>
</div>
@endsection
