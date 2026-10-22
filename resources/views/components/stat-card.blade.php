@props(['label', 'value', 'icon', 'tone', 'note', 'trend' => 'up', 'href' => null])
<article class="stat-card stat-{{ $tone }}">
    @if($href)<a href="{{ $href }}" aria-label="{{ $label }}" class="stat-card-link">@endif
    <div class="stat-top">
        <span class="stat-icon"><x-icon :name="$icon" size="18" /></span>
        <x-icon :name="$icon" size="36" class="stat-watermark" />
    </div>
    <div class="stat-body">
        <span class="stat-label">{{ $label }}</span>
        <div class="stat-value">{{ $value }}</div>
        <div class="stat-note">
            @if(str_contains($note, '↓') || $trend === 'down' || str_contains(strtolower($label), 'pending'))
                <span class="trend-arrow">&darr;</span>
            @else
                <span class="trend-arrow">&uarr;</span>
            @endif
            <span>{{ ltrim($note, '↑↓ ') }}</span>
        </div>
    </div>
    @if($href)</a>@endif
</article>
