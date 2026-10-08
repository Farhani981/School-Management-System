@props(['status'])

@php
    $status = trim((string) $status);
    $normalized = strtolower($status);
    
    if (in_array($normalized, ['active'])) {
        $classes = 'bg-emerald-50 text-emerald-700 border-emerald-200';
        $dotClass = 'bg-emerald-500';
        $label = 'Active';
    } elseif (in_array($normalized, ['left', 'left school'])) {
        $classes = 'bg-rose-50 text-rose-700 border-rose-200';
        $dotClass = 'bg-rose-500';
        $label = 'Left';
    } elseif (in_array($normalized, ['passed out', 'graduated'])) {
        $classes = 'bg-sky-50 text-sky-700 border-sky-200';
        $dotClass = 'bg-sky-500';
        $label = 'Passed out';
    } elseif (in_array($normalized, ['inactive'])) {
        $classes = 'bg-amber-50 text-amber-700 border-amber-200';
        $dotClass = 'bg-amber-500';
        $label = 'Inactive';
    } else {
        $classes = 'bg-slate-50 text-slate-700 border-slate-200';
        $dotClass = 'bg-slate-400';
        $label = $status ?: 'Unknown';
    }
@endphp

<span class="inline-flex items-center gap-1.5 px-3 py-1 text-xs font-semibold rounded-full border {{ $classes }} shadow-sm">
    <span class="w-1.5 h-1.5 rounded-full {{ $dotClass }}"></span>
    <span>{{ $label }}</span>
</span>
