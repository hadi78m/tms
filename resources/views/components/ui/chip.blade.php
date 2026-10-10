@props([
    'label' => null,
    'active' => false,
    'as' => 'span',
    'color' => null,
    'size' => 'md',
])

@php
$sizes = [
    'sm' => 'text-[11px] px-2.5 py-1',
    'md' => 'text-xs px-3 py-1.5',
];

$size = $sizes[$size] ?? $sizes['md'];

// رنگ فقط یک مقدار بصری اختیاری (hex) است؛ منطق فیلتر در اینجا نیست.
$inlineStyle = ($active && isset($color))
    ? sprintf('border-color: %s; color: %s; background-color: %s14;', $color, $color, $color)
    : null;

$inactiveClass = 'border-line bg-surface-base text-ink-muted transition-all duration-200 hover:-translate-y-0.5 hover:border-line-strong hover:text-ink';
$activeClass = isset($color)
    ? 'border bg-surface-base'
    : 'border border-transparent bg-primary text-white shadow-ui-soft';
@endphp

@if ($as === 'button')
    <button type="button"
            @isset($inlineStyle) style="{{ $inlineStyle }}" @endisset
            {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 rounded-full font-bold {$size} " . ($active ? $activeClass : $inactiveClass)]) }}>
        {{ $label ?? $slot }}
    </button>
@else
    <span @isset($inlineStyle) style="{{ $inlineStyle }}" @endisset
          {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 rounded-full border font-bold {$size} " . ($active ? $activeClass : $inactiveClass)]) }}>
        {{ $label ?? $slot }}
    </span>
@endif
