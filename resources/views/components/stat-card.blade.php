@props(['label', 'value', 'icon', 'tone', 'note', 'href' => null])
<article class="stat-card stat-{{ $tone }}">
    @if($href)<a href="{{ $href }}" aria-label="{{ $label }}" style="display:block;color:inherit;text-decoration:none">@endif
    <div class="stat-top"><span class="stat-icon"><x-icon :name="$icon" size="18" /></span><span class="stat-period">Overview</span></div>
    <div class="stat-value">{{ $value }}</div>
    <h2 class="stat-label">{{ $label }}</h2>
    <div class="stat-note"><x-icon name="arrow" size="12" />{{ $note }}</div>
    <x-icon :name="$icon" size="88" class="stat-watermark" />
    @if($href)</a>@endif
</article>
