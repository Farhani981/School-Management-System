@props([
    'percent' => 0,
    'size' => 84,
    'strokeWidth' => 8,
    'color' => '#2563EB',
    'label' => null,
])

@php
    $radius = ($size - $strokeWidth) / 2;
    $circumference = 2 * pi() * $radius;
    $offset = $circumference - ($percent / 100) * $circumference;
@endphp

<div {{ $attributes->merge(['class' => 'portal-progress-ring-container']) }} style="width: {{ $size }}px; height: {{ $size }}px;">
    <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 {{ $size }} {{ $size }}">
        <!-- Background Track -->
        <circle
            cx="{{ $size / 2 }}"
            cy="{{ $size / 2 }}"
            r="{{ $radius }}"
            stroke="#E2E8F0"
            stroke-width="{{ $strokeWidth }}"
            fill="transparent"
        />
        <!-- Progress Bar -->
        <circle
            cx="{{ $size / 2 }}"
            cy="{{ $size / 2 }}"
            r="{{ $radius }}"
            stroke="{{ $color }}"
            stroke-width="{{ $strokeWidth }}"
            stroke-dasharray="{{ $circumference }}"
            stroke-dashoffset="{{ $offset }}"
            stroke-linecap="round"
            fill="transparent"
            transform="rotate(-90 {{ $size / 2 }} {{ $size / 2 }})"
            style="transition: stroke-dashoffset 0.6s ease-in-out;"
        />
    </svg>
    <div class="portal-progress-ring-text">
        <span style="font-size: {{ max(12, floor($size / 4)) }}px;">{{ round($percent) }}%</span>
        @if($label)
            <span class="portal-progress-ring-label">{{ $label }}</span>
        @endif
    </div>
</div>
