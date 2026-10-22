@props([
    'title',
    'value',
    'icon' => 'chart',
    'color' => 'primary',
    'trend' => null,
    'trendType' => 'positive',
    'subtext' => null,
])

<div {{ $attributes->merge(['class' => 'portal-stat-card']) }}>
    <div class="portal-stat-header">
        <span class="portal-stat-title">{{ $title }}</span>
        <div class="portal-stat-icon-wrap {{ $color }}">
            <x-icon :name="$icon" size="22" />
        </div>
    </div>
    <div class="portal-stat-body">
        <span class="portal-stat-value">{{ $value }}</span>
    </div>
    @if($trend || $subtext)
        <div class="portal-stat-footer">
            @if($trend)
                <span class="portal-stat-trend {{ $trendType }}">{{ $trend }}</span>
            @endif
            @if($subtext)
                <span class="portal-stat-subtext">{{ $subtext }}</span>
            @endif
        </div>
    @endif
</div>
