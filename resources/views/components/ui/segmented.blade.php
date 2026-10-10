@props([
    'name' => null,
    'value' => null,
    'options' => [],
    'size' => 'md',
])

@php
$sizes = [
    'sm' => 'text-xs px-3 py-1.5',
    'md' => 'text-[13px] px-3.5 py-2',
];

$size = $sizes[$size] ?? $sizes['md'];

// نرمال‌سازی گزینه‌ها: ['value' => 'label'] یا [['value' => .., 'label' => ..]]
$normalized = [];
foreach ($options as $key => $option) {
    if (is_array($option)) {
        $normalized[] = [
            'value' => $option['value'] ?? $key,
            'label' => $option['label'] ?? ($option['value'] ?? $key),
        ];
    } else {
        $normalized[] = ['value' => $key, 'label' => $option];
    }
}
@endphp

<div x-data="{ current: @js($value) }"
     {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 rounded-ui-md border border-line bg-surface-inset p-1 shadow-inner']) }}>
    @isset($name)
        <input type="hidden" name="{{ $name }}" x-bind:value="current">
    @endisset

    @foreach ($normalized as $option)
        <button type="button"
                @click="current = @js($option['value'])"
                :class="current === @js($option['value'])
                    ? 'bg-primary text-white shadow-ui-soft'
                    : 'text-ink-muted transition-colors duration-200 hover:bg-surface-base hover:text-ink'"
                class="inline-flex items-center justify-center gap-2 rounded-ui font-bold {{ $size }}">
            {{ $option['label'] }}
        </button>
    @endforeach
</div>
