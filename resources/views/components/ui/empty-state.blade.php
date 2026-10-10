@props([
    'title' => null,
    'description' => null,
])

<div {{ $attributes->merge(['class' => 'grid place-items-center rounded-ui-md border border-dashed border-line-strong bg-surface-inset/60 px-6 py-12 text-center']) }}>
    @if (isset($icon))
        <span class="grid h-12 w-12 place-items-center rounded-ui-md bg-surface-base text-ink-subtle shadow-ui-soft">
            {{ $icon }}
        </span>
    @elseif (! isset($title) && ! isset($description))
        {{-- آیکون پیش‌فرض وقتی محتوایی داده نشده باشد --}}
        <span class="grid h-12 w-12 place-items-center rounded-ui-md bg-surface-base text-ink-subtle shadow-ui-soft">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-3.5l-1.5 2.5h-5L9 13H4" />
            </svg>
        </span>
    @endif

    @isset($title)
        <p class="mt-3 text-sm font-bold text-ink">{{ $title }}</p>
    @endisset

    @isset($description)
        <p class="mt-1 max-w-sm text-xs leading-relaxed text-ink-muted">{{ $description }}</p>
    @endisset

    @if (isset($action))
        <div class="mt-4">
            {{ $action }}
        </div>
    @endif

    {{ $slot }}
</div>
