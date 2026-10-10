@props([
    'label' => null,
])

<div {{ $attributes->merge(['class' => 'rounded-ui border border-line-subtle bg-surface-base px-3 py-2']) }}>
    @isset($label)
        <p class="text-[11px] font-semibold text-ink-subtle">{{ $label }}</p>
    @endisset
    <div class="mt-0.5 text-sm font-bold text-ink">
        {{ $slot }}
    </div>
</div>
