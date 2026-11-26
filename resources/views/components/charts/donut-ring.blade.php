@props([
    'percentage' => 0,
    'size' => 88,
    'strokeWidth' => 8,
    'color' => '#2563eb',
    'label' => null,
    'sublabel' => null,
])

@php
    $normalized = max(0.0, min(100.0, (float) $percentage));
    $sizePx = max(48, (int) $size);
    $stroke = max(4, (int) $strokeWidth);
    $center = $sizePx / 2;
    $radius = ($sizePx - $stroke) / 2;
    $circumference = 2 * pi() * $radius;
    $dashOffset = $circumference * (1 - ($normalized / 100));
    $displayLabel = $label ?? (number_format($normalized, 1) . '%');
@endphp

<div {{ $attributes->class(['relative inline-flex shrink-0 items-center justify-center']) }} style="width: {{ $sizePx }}px; height: {{ $sizePx }}px;">
    <svg
        width="{{ $sizePx }}"
        height="{{ $sizePx }}"
        viewBox="0 0 {{ $sizePx }} {{ $sizePx }}"
        class="-rotate-90"
        role="img"
        aria-label="{{ $displayLabel }}"
    >
        <circle
            cx="{{ $center }}"
            cy="{{ $center }}"
            r="{{ $radius }}"
            fill="none"
            stroke="currentColor"
            stroke-width="{{ $stroke }}"
            class="text-slate-200 dark:text-slate-800"
        />
        <circle
            cx="{{ $center }}"
            cy="{{ $center }}"
            r="{{ $radius }}"
            fill="none"
            stroke="{{ $color }}"
            stroke-width="{{ $stroke }}"
            stroke-linecap="round"
            stroke-dasharray="{{ number_format($circumference, 3, '.', '') }}"
            stroke-dashoffset="{{ number_format($dashOffset, 3, '.', '') }}"
            style="transition: stroke-dashoffset 260ms ease;"
        />
    </svg>

    <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center text-center leading-none">
        <span class="text-xs font-bold tabular-nums text-slate-900 dark:text-white">{{ $displayLabel }}</span>
        @if (filled($sublabel))
            <span class="mt-0.5 text-[10px] font-medium text-slate-500 dark:text-slate-400">{{ $sublabel }}</span>
        @endif
    </div>
</div>

