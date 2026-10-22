@props([
    'title' => 'Coming Soon',
    'description' => 'This section is being prepared with exciting features. Check back soon!',
    'icon' => 'book',
    'actionText' => 'Back to Dashboard',
    'actionUrl' => null,
    'badge' => 'Work In Progress',
])

<div {{ $attributes->merge(['class' => 'portal-empty-state']) }}>
    <div class="portal-empty-icon">
        <x-icon :name="$icon" size="36" />
    </div>
    @if($badge)
        <span class="portal-badge info" style="margin-bottom: 12px;">{{ $badge }}</span>
    @endif
    <h3 class="portal-empty-title">{{ $title }}</h3>
    <p class="portal-empty-desc">{{ $description }}</p>
    @if($actionText)
        <a href="{{ $actionUrl ?? route('portal.home') }}" class="portal-btn-primary">
            <x-icon name="dashboard" size="16" />
            <span>{{ $actionText }}</span>
        </a>
    @endif
</div>
