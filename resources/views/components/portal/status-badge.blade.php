@props([
    'variant' => 'info',
    'dot' => true,
])

<span {{ $attributes->merge(['class' => 'portal-badge ' . $variant]) }}>
    @if($dot)
        <span class="portal-badge-dot"></span>
    @endif
    <span>{{ $slot }}</span>
</span>
