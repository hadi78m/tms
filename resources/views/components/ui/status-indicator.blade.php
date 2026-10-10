@props([
    'label' => null,
    'variant' => 'default',
    'size' => 'sm',
    'dot' => true,
])

@php
// فقط یک نمایشگر بصری — هیچ نگاشتی از وضعیت‌های دامنه در اینجا وجود ندارد.
$variants = [
    'default' => 'bg-ink-muted',
    'muted'   => 'bg-ink-subtle',
    'success' => 'bg-success-500',
    'warning' => 'bg-warning-500',
    'danger'  => 'bg-danger-500',
    'info'    => 'bg-info-500',
    'primary' => 'bg-primary',
];

$dots = [
    'sm' => 'h-2 w-2',
    'md' => 'h-2.5 w-2.5',
];

$texts = [
    'sm' => 'text-xs',
    'md' => 'text-sm',
];

$variant = $variants[$variant] ?? $variants['default'];
$dot = $dots[$size] ?? $dots['sm'];
$text = $texts[$size] ?? $texts['sm'];
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-2 font-medium text-ink-muted {$text}"]) }}>
    @if ($dot)
        <span class="rounded-full {{ $dot }} {{ $variant }}" aria-hidden="true"></span>
    @endif
    @isset($label)
        {{ $label }}
    @endisset
    {{ $slot }}
</span>
