@props(['title', 'icon' => null, 'badge' => null])

<div {{ $attributes->merge(['class' => 'bg-white rounded-xl border border-slate-200/80 shadow-[0_2px_10px_-3px_rgba(6,24,44,0.05)] overflow-hidden transition-all duration-200']) }}>
    <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between gap-3 bg-white">
        <div class="flex items-center gap-2.5">
            @if($icon)
                <span class="text-[#0A7EA4] flex shrink-0">{!! $icon !!}</span>
            @endif
            <h3 class="text-sm font-bold text-slate-800 tracking-tight">{{ $title }}</h3>
            @if($badge)
                <span class="text-[11px] font-medium px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">{{ $badge }}</span>
            @endif
        </div>
        @if(isset($action))
            <div class="flex items-center gap-2 text-xs">
                {{ $action }}
            </div>
        @endif
    </div>
    <div class="p-5">
        {{ $slot }}
    </div>
</div>
