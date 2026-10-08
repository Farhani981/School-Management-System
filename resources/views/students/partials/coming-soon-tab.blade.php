@props(['module' => 'Exam', 'description' => 'This module will be connected in an upcoming release.'])

<div class="bg-white rounded-xl border border-slate-200/80 shadow-[0_2px_10px_-3px_rgba(6,24,44,0.05)] p-12 text-center">
    <div class="w-16 h-16 rounded-2xl bg-[#e8f7fa] text-[#0A7EA4] mx-auto flex items-center justify-center mb-4 ring-8 ring-[#f0f9fb]">
        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
    </div>
    <h3 class="text-base font-bold text-slate-800 mb-1">{{ $module }} &bull; Coming Soon</h3>
    <p class="text-xs text-slate-500 max-w-sm mx-auto mb-5">{{ $description }}</p>
    <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-600">
        <span class="w-2 h-2 rounded-full bg-[#0A7EA4] animate-pulse"></span>
        <span>Module in progress</span>
    </div>
</div>
