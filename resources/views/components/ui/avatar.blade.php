@props([
    'src' => null,
    'name' => null,
    'alt' => null,
    'size' => 'md',
    'color' => null,
])

@php
$sizes = [
    'xs' => 'h-6 w-6 text-[10px]',
    'sm' => 'h-8 w-8 text-xs',
    'md' => 'h-10 w-10 text-sm',
    'lg' => 'h-14 w-14 text-base',
];

$size = $sizes[$size] ?? $sizes['md'];
$fallback = $color ?? '#475569';

// ساختن حروف اول از نام — فقط پردازش رشته، بدون هیچ وابستگی به مدل
$initials = '?';
if (filled($name)) {
    $parts = preg_split('/\s+/u', trim((string) $name));
    $parts = array_values(array_filter($parts, fn ($part) => $part !== ''));
    if ($parts !== []) {
        $initials = mb_substr($parts[0], 0, 1, 'UTF-8');
        if (count($parts) > 1) {
            $initials .= mb_substr($parts[1], 0, 1, 'UTF-8');
        }
    }
}
@endphp

<span {{ $attributes->merge(['class' => "relative inline-grid shrink-0 place-items-center overflow-hidden rounded-full {$size}"]) }}>
    @isset($src)
        <img src="{{ $src }}" alt="{{ $alt ?? $name ?? '' }}" class="h-full w-full object-cover">
    @else
        <span class="grid h-full w-full place-items-center rounded-full font-bold text-white ring-2 ring-white/70"
              style="background: linear-gradient(140deg, {{ $fallback }}, {{ $fallback }}bb);"
              title="{{ $name ?? '' }}">
            {{ $initials }}
        </span>
    @endisset
</span>
