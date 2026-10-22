@props([
    'type' => 'success',
    'message' => null,
    'dismissible' => true,
])

<div {{ $attributes->merge(['class' => 'portal-toast ' . $type]) }} data-auto-dismiss>
    <div class="portal-toast-icon">
        @if($type === 'success')
            <x-icon name="check" size="18" />
        @elseif($type === 'danger')
            <x-icon name="help" size="18" />
        @elseif($type === 'warning')
            <x-icon name="bell" size="18" />
        @else
            <x-icon name="help" size="18" />
        @endif
    </div>
    <div class="portal-toast-content" style="flex: 1;">
        {{ $message ?? $slot }}
    </div>
    @if($dismissible)
        <button type="button" onclick="this.closest('.portal-toast').remove()" style="background:none;border:none;color:var(--portal-muted);cursor:pointer;padding:2px;" aria-label="Close">
            &times;
        </button>
    @endif
</div>
