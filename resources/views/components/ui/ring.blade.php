@props([
    'value' => 0,
    'max' => 100,
    'size' => 64,
    'stroke' => 8,
    'color' => '#4F46E5',
    'track' => '#e2e8f0',
])

@php
// فقط نمایش — هیچ محاسبهٔ پیشرفت دامنه‌ای در اینجا انجام نمی‌شود.
$max = max((float) $max, 0);
$percent = $max > 0 ? min(100, max(0, ((float) $value / $max) * 100)) : 0;

$radius = max(((int) $size - (int) $stroke) / 2, 1);
$circumference = 2 * M_PI * $radius;
$offset = $circumference * (1 - $percent / 100);
@endphp

<span class="relative inline-grid shrink-0 place-items-center"
      style="width: {{ (int) $size }}px; height: {{ (int) $size }}px;"
      role="progressbar"
      aria-valuenow="{{ round($percent) }}"
      aria-valuemin="0"
      aria-valuemax="100">
    <svg class="-rotate-90" style="width: {{ (int) $size }}px; height: {{ (int) $size }}px;" aria-hidden="true">
        <circle cx="50%" cy="50%" r="{{ $radius }}"
                fill="none"
                stroke="{{ $track }}"
                stroke-width="{{ (int) $stroke }}"></circle>
        <circle cx="50%" cy="50%" r="{{ $radius }}"
                fill="none"
                stroke="{{ $color }}"
                stroke-width="{{ (int) $stroke }}"
                stroke-linecap="round"
                stroke-dasharray="{{ $circumference }}"
                stroke-dashoffset="{{ $offset }}"
                style="transition: stroke-dashoffset 0.6s ease-out;"></circle>
    </svg>
    <span class="absolute inset-0 grid place-items-center text-center">
        {{ $slot }}
    </span>
</span>
