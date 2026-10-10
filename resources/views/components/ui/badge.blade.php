@props([
    'variant' => 'default',
    'size' => 'sm',
    'dot' => false,
])

@php
$variants = [
    'default' => 'bg-surface-inset text-ink-muted ring-line-strong',
    'muted'   => 'bg-surface-inset text-ink-subtle ring-line',
    'success' => 'bg-success-100 text-success-700 ring-success-200',
    'warning' => 'bg-warning-100 text-warning-700 ring-warning-200',
    'danger'  => 'bg-danger-100 text-danger-700 ring-danger-200',
    'info'    => 'bg-info-100 text-info-700 ring-info-200',
    'primary' => 'bg-primary/10 text-primary ring-primary/20',
];

$sizes = [
    'sm' => 'text-[11px] px-2 py-0.5',
    'md' => 'text-xs px-2.5 py-1',
];

$variant = $variants[$variant] ?? $variants['default'];
$size = $sizes[$size] ?? $sizes['sm'];
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 rounded-full font-bold whitespace-nowrap ring-1 ring-inset {$variant} {$size}"]) }}>
    @if ($dot)
        <span class="h-1.5 w-1.5 rounded-full bg-current" aria-hidden="true"></span>
    @endif
    {{ $slot }}
</span>
