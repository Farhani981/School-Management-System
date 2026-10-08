@props(['items' => []])

<div {{ $attributes->merge(['class' => 'grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3.5 text-xs']) }}>
    @foreach($items as $label => $value)
        <div class="flex items-baseline gap-2">
            <span class="font-bold text-slate-800 shrink-0 min-w-[105px]">{{ $label }}</span>
            <span class="text-slate-400 font-medium select-none shrink-0">:</span>
            <span class="text-slate-600 truncate flex-1 font-normal">
                @if(blank($value) || $value === 'null')
                    <span class="text-slate-400 italic">Not provided</span>
                @else
                    {!! $value !!}
                @endif
            </span>
        </div>
    @endforeach
    {{ $slot }}
</div>
