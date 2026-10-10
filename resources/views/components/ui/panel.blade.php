@props([
    'title' => null,
    'subtitle' => null,
    'icon' => null,
    'variant' => 'elevated',
    'accent' => null,
    'bodyClass' => '',
    'headerClass' => '',
])

@php
$variants = [
    'elevated' => 'bg-surface-base border border-line shadow-ui-soft',
    'bordered' => 'bg-surface-base border border-line-strong',
    'flat'     => 'bg-surface-inset border border-line-subtle',
];

$variant = $variants[$variant] ?? $variants['elevated'];
$hasHeader = isset($title) || isset($subtitle) || isset($icon) || isset($header) || isset($actions);
@endphp

<section {{ $attributes->merge(['class' => "relative overflow-hidden rounded-ui-md {$variant}"]) }}>
    @isset($accent)
        <span class="absolute inset-y-0 start-0 w-1.5" style="background: {{ $accent }};" aria-hidden="true"></span>
    @endisset

    @if ($hasHeader)
        @if (isset($header))
            {{ $header }}
        @else
            <header class="flex flex-wrap items-center justify-between gap-3 border-b border-line-subtle px-5 py-3.5 {{ $headerClass }}">
                <div class="flex items-center gap-3">
                    @isset($icon)
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-ui bg-primary/10 text-primary ring-1 ring-primary/20">
                            {{ $icon }}
                        </span>
                    @endisset
                    <div>
                        @isset($title)
                            <h3 class="text-[15px] font-extrabold leading-tight text-ink">{{ $title }}</h3>
                        @endisset
                        @isset($subtitle)
                            <p class="mt-0.5 text-xs text-ink-muted">{{ $subtitle }}</p>
                        @endisset
                    </div>
                </div>
                {{ $actions ?? '' }}
            </header>
        @endif
    @endif

    <div class="{{ $bodyClass ?: 'p-5' }}">
        {{ $slot }}
    </div>

    @if (isset($footer))
        {{ $footer }}
    @endif
</section>
