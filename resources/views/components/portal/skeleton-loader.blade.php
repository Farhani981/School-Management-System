@props([
    'type' => 'card',
    'count' => 1,
])

<div {{ $attributes->merge(['class' => 'portal-skeleton-wrap']) }}>
    @for($i = 0; $i < $count; $i++)
        @if($type === 'card')
            <div class="portal-skeleton portal-skeleton-card" style="margin-bottom: 16px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 20px;">
                    <div class="portal-skeleton portal-skeleton-line short" style="height: 12px;"></div>
                    <div class="portal-skeleton portal-skeleton-circle" style="width: 36px; height: 36px;"></div>
                </div>
                <div class="portal-skeleton portal-skeleton-line" style="height: 28px; width: 45%;"></div>
            </div>
        @elseif($type === 'table')
            <div style="background: white; border: 1px solid var(--portal-border); border-radius: var(--portal-radius); padding: 16px; margin-bottom: 16px;">
                <div class="portal-skeleton portal-skeleton-line" style="height: 20px; width: 30%; margin-bottom: 16px;"></div>
                @for($r = 0; $r < 4; $r++)
                    <div style="display: flex; gap: 16px; margin-bottom: 12px;">
                        <div class="portal-skeleton portal-skeleton-line" style="flex: 1; height: 16px;"></div>
                        <div class="portal-skeleton portal-skeleton-line" style="flex: 2; height: 16px;"></div>
                        <div class="portal-skeleton portal-skeleton-line" style="flex: 1; height: 16px;"></div>
                    </div>
                @endfor
            </div>
        @elseif($type === 'avatar')
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                <div class="portal-skeleton portal-skeleton-circle"></div>
                <div style="flex: 1;">
                    <div class="portal-skeleton portal-skeleton-line medium" style="height: 14px;"></div>
                    <div class="portal-skeleton portal-skeleton-line short" style="height: 10px;"></div>
                </div>
            </div>
        @else
            <div class="portal-skeleton portal-skeleton-line medium" style="height: 16px; margin-bottom: 8px;"></div>
        @endif
    @endfor
</div>
