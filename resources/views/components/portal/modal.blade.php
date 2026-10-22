@props([
    'id',
    'title' => 'Modal',
])

<div id="{{ $id }}" {{ $attributes->merge(['class' => 'portal-modal-backdrop']) }} role="dialog" aria-modal="true" aria-labelledby="{{ $id }}-title">
    <div class="portal-modal-card">
        <div class="portal-modal-header">
            <h3 id="{{ $id }}-title" class="portal-modal-title">{{ $title }}</h3>
            <button type="button" class="portal-modal-close" data-portal-modal-close aria-label="Close modal">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="portal-modal-body">
            {{ $slot }}
        </div>
        @if(isset($footer))
            <div class="portal-modal-footer">
                {{ $footer }}
            </div>
        @endif
    </div>
</div>
