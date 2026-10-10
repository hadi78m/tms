@props([
    'value' => 0,
    'max' => 100,
    'variant' => 'primary',
    'size' => 'md',
    'label' => false,
    'stripe' => false,
])

@php
// فقط نمایش — هیچ محاسبهٔ پیشرفت دامنه‌ای در اینجا انجام نمی‌شود.
$variants = [
    'primary'   => 'bg-primary',
    'secondary' => 'bg-secondary',
    'success'   => 'bg-success-500',
    'warning'   => 'bg-warning-500',
    'danger'    => 'bg-danger-500',
    'info'      => 'bg-info-500',
];

$sizes = [
    'xs' => 'h-1',
    'sm' => 'h-1.5',
    'md' => 'h-2',
    'lg' => 'h-3',
];

$max = max((float) $max, 0);
$value = (float) $value;
$percent = $max > 0 ? min(100, max(0, ($value / $max) * 100)) : 0;

$variant = $variants[$variant] ?? $variants['primary'];
$size = $sizes[$size] ?? $sizes['md'];
@endphp

<div {{ $attributes->merge(['class' => 'flex items-center gap-2']) }}>
    <div class="block w-full overflow-hidden rounded-full bg-surface-inset {{ $size }}"
         role="progressbar"
         aria-valuenow="{{ round($percent) }}"
         aria-valuemin="0"
         aria-valuemax="100">
        <div class="block h-full rounded-full transition-[width] duration-500 ease-out {{ $variant }}"
             style="width: {{ round($percent, 2) }}%;"></div>
    </div>
    @if ($label)
        <span class="shrink-0 text-[11px] font-bold tabular-nums text-ink-muted">{{ round($percent) }}٪</span>
    @endif
</div>
