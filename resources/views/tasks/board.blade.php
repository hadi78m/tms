@extends('layouts.app')

@use('App\Support\TaskBoard\BoardColumns')

@section('title', 'تابلوی وظایف')
@section('header_title', 'تابلوی وظایف')

@section('content')
<div class="mx-auto flex h-full max-w-[1600px] flex-col space-y-4">
    {{-- ================================================================
         Board Header (gate §18): title, REAL total count, 4 filters only.
         No KPI, no SLA dashboard, no workload/progress summary.
    ================================================================ --}}
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-ink">تابلوی وظایف</h1>
                <span class="inline-flex items-center rounded-full bg-surface-inset px-2.5 py-1 text-xs font-bold text-ink-muted ring-1 ring-inset ring-line">
                    {{ $tasks->total() }} وظیفه
                </span>
            </div>

            {{-- View Switcher (gate §19): Table | Board --}}
            <div class="inline-flex items-center gap-1 rounded-ui-md border border-line bg-surface-inset p-1">
                <a href="{{ route('tasks.index') }}"
                   class="inline-flex items-center gap-1.5 rounded-ui px-3.5 py-1.5 text-[13px] font-bold text-ink-muted transition-colors hover:bg-surface-base hover:text-ink">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                    جدول
                </a>
                <span aria-current="true"
                      class="inline-flex items-center gap-1.5 rounded-ui bg-primary px-3.5 py-1.5 text-[13px] font-bold text-white shadow-ui-soft">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5h3v14H4zM10 5h3v9h-3zM16 5h3v12h-3z" />
                    </svg>
                    تابلو
                </span>
            </div>
        </div>

        {{-- Filters: real, server-side, deep-linkable (gate §15) --}}
        <form method="GET" action="{{ route('tasks.board') }}" class="space-y-3" role="search">
            <div class="flex flex-wrap items-end gap-3">
                <div class="grow basis-56">
                    <label for="board-search" class="mb-1 block text-xs font-bold text-ink-muted">جستجو</label>
                    <div class="relative">
                        <svg class="pointer-events-none absolute inset-y-0 start-3 my-auto h-4 w-4 text-ink-subtle" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <input id="board-search"
                               type="search"
                               name="search"
                               value="{{ $filters['search'] }}"
                               placeholder="جستجو بر اساس عنوان وظیفه…"
                               class="w-full rounded-ui border-line bg-surface-base py-2 ps-9 pe-3 text-sm text-ink shadow-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/30">
                    </div>
                </div>

                <div class="basis-44 grow">
                    <label for="board-project" class="mb-1 block text-xs font-bold text-ink-muted">پروژه</label>
                    <select id="board-project"
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
                    <label for="board-status" class="mb-1 block text-xs font-bold text-ink-muted">وضعیت</label>
                    <select id="board-status"
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
                    <label for="board-priority" class="mb-1 block text-xs font-bold text-ink-muted">اولویت</label>
                    <select id="board-priority"
                            name="priority"
                            class="w-full rounded-ui border-line bg-surface-base py-2 px-3 text-sm text-ink shadow-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/30">
                        <option value="">همه اولویت‌ها</option>
                        @foreach (\App\Domain\Enums\TaskPriority::cases() as $priority)
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
            </div>
        </form>
    </div>

    {{-- ================================================================
         Board body.
         Desktop: 5 columns. Mobile: stacked (gate §22).
         RTL ordering (gate §11): DOM order = lifecycle order
         (آماده شروع → … → لغو شده). The visual placement in RTL is handled
         by the natural inline-start flow of a flex row, so the lifecycle
         reads start→end for a Persian reader. On narrow screens the row
         collapses to a single stacked column.

         The 5 columns ALWAYS render (each shows its own per-column empty
         state per gate §17) — a board with no columns is not a board. When
         the whole filtered query is empty, a global empty-state banner is
         shown ABOVE the columns (gate §17).
    ================================================================ --}}
    @if ($tasks->isEmpty())
        <x-ui.empty-state title="نتیجه‌ای یافت نشد"
                          description="هیچ وظیفه‌ای با فیلترهای فعلی مطابقت ندارد. فیلترها را تغییر دهید." />
    @endif

    <div class="flex flex-col gap-4 xl:grid xl:grid-cols-5 xl:items-start">
        @foreach ($boardColumns as $key => $column)
            <x-tasks.board-column
                :column="$key"
                :label="$column['label']"
                :tasks="$grouped[$key]"
                :variant="$column['variant']"
                :collapsible="$key === 'cancelled'"
                :collapsed="$key === 'cancelled'" />
        @endforeach
    </div>

    @if ($tasks->hasPages())
        <div class="flex justify-center border-t border-line-subtle pt-4">
            {{ $tasks->links() }}
        </div>
    @endif
</div>

{{-- ================================================================
     Phase 4 — Task Drawer host (gate §20/§21/§22/§23).
     Fetch-on-open through the EXISTING tasks.show route with
     ?view=drawer (gate §29 — Option B). No second endpoint, no
     second authorization layer: the server-side TaskPolicy::view
     stays the single source of truth. This shell only transports
     already-authorized HTML and holds the loading/error states.
==================================================== --}}
<div x-data="{
        loading: false,
        error: null,
        html: '',
        url: null,
        async open(url) {
            this.url = url;
            this.loading = true;
            this.error = null;
            this.html = '';
            // Focus entry / focus trap / focus return are owned by the reusable
            // drawer shell (elements/drawer) — this host only gives it state.
            window.dispatchEvent(new CustomEvent('open-drawer', { detail: 'task-detail' }));
            await this.load();
        },
        async retry() {
            this.loading = true;
            this.error = null;
            this.html = '';
            await this.load();
        },
        async load() {
            try {
                const response = await window.fetch(this.url, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' },
                    credentials: 'same-origin',
                });

                if (response.ok) {
                    this.html = await response.text();
                } else if (response.status === 403) {
                    this.error = 'forbidden';
                } else if (response.status === 404) {
                    this.error = 'not_found';
                } else {
                    this.error = 'generic';
                }
            } catch (e) {
                this.error = 'network';
            }

            this.loading = false;
        },
     }"
     x-on:open-task-drawer.window="open($event.detail)">

    <x-elements.drawer name="task-detail" side="left" width="sm:max-w-[430px]" label="بستن پنل جزئیات وظیفه">
        <div class="flex h-full flex-col">
            {{-- Screen-reader dialog title (the visible title arrives with
                 the fetched partial — gate §23). --}}
            <h2 class="sr-only">جزئیات وظیفه</h2>

            {{-- Vertically scrollable content region (gate §6). The panel
                 itself is flex flex-col + overflow-hidden from the Phase 1
                 foundation, so this region owns the scroll. --}}
            <div tabindex="-1"
                 role="dialog"
                 aria-modal="true"
                 aria-labelledby="task-drawer-title"
                 aria-busy="false"
                 :aria-busy="loading ? 'true' : 'false'"
                 class="flex-1 overflow-y-auto p-4 outline-none">

                {{-- Loading state (gate §21) --}}
                <div x-show="loading" class="space-y-4" aria-live="polite">
                    <div class="space-y-2 border-b border-line-subtle pb-4">
                        <div class="h-3 w-12 animate-pulse rounded-full bg-surface-inset"></div>
                        <div class="h-5 w-3/4 animate-pulse rounded-full bg-surface-inset"></div>
                        <div class="h-5 w-1/2 animate-pulse rounded-full bg-surface-inset"></div>
                    </div>
                    <div class="grid grid-cols-2 gap-2.5">
                        <div class="h-16 animate-pulse rounded-ui bg-surface-inset"></div>
                        <div class="h-16 animate-pulse rounded-ui bg-surface-inset"></div>
                        <div class="h-16 animate-pulse rounded-ui bg-surface-inset"></div>
                        <div class="h-16 animate-pulse rounded-ui bg-surface-inset"></div>
                    </div>
                    <div class="h-20 animate-pulse rounded-ui bg-surface-inset"></div>
                    <div class="h-20 animate-pulse rounded-ui bg-surface-inset"></div>
                    <p class="pt-2 text-center text-xs text-ink-subtle">در حال دریافت اطلاعات وظیفه…</p>
                </div>

                {{-- Error state (gate §21): read-only, no internals, no
                     retry against another endpoint. --}}
                <div x-show="error" class="space-y-4" aria-live="assertive">
                    <div class="rounded-ui border border-danger-200 bg-danger-50 p-6 text-center">
                        <svg class="mx-auto h-10 w-10 text-danger-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                        </svg>
                        <p class="mt-3 text-sm font-bold text-danger-800">
                            <template x-if="error === 'forbidden'">شما دسترسی به این وظیفه ندارید.</template>
                            <template x-if="error === 'not_found'">وظیفه مورد نظر یافت نشد.</template>
                            <template x-if="error === 'network'">اتصال به سرور برقرار نشد. اتصال خود را بررسی کنید.</template>
                            <template x-if="error === 'generic'">خطایی در دریافت اطلاعات رخ داد.</template>
                        </p>
                    </div>
                    <button type="button"
                            x-on:click="retry()"
                            x-show="error === 'network' || error === 'generic'"
                            class="inline-flex w-full items-center justify-center gap-2 rounded-ui border border-line bg-surface-base px-4 py-2.5 text-sm font-bold text-ink transition hover:border-line-strong hover:bg-surface-inset focus:outline-none focus-visible:ring-2 focus-visible:ring-primary">
                        تلاش دوباره
                    </button>
                    <button type="button"
                            x-on:click="$dispatch('close-drawer', 'task-detail')"
                            x-show="error === 'forbidden' || error === 'not_found'"
                            class="inline-flex w-full items-center justify-center gap-2 rounded-ui border border-line bg-surface-base px-4 py-2.5 text-sm font-bold text-ink transition hover:border-line-strong hover:bg-surface-inset focus:outline-none focus-visible:ring-2 focus-visible:ring-primary">
                        بستن
                    </button>
                </div>

                {{-- Loaded read-only drawer content --}}
                <div x-show="!loading && !error" x-html="html" class="space-y-5"></div>
            </div>
        </div>
    </x-elements.drawer>
</div>
@endsection
