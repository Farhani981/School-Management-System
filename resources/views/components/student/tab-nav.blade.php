@props(['activeTab' => 'details', 'canViewFees' => false])

@php
    $tabs = [
        'details' => [
            'label' => 'Student Details',
            'icon' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>',
        ],
        'attendance' => [
            'label' => 'Attendance',
            'icon' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>',
        ],
    ];

    if ($canViewFees) {
        $tabs['fees'] = [
            'label' => 'Fees',
            'icon' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>',
        ];
    }

$tabs['progress'] = [
        'label' => 'Progress',
        'icon' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>',
    ];
@endphp

<div class="border-b border-slate-200 overflow-x-auto no-scrollbar my-5">
    <nav class="flex space-x-1 sm:space-x-4 min-w-max" aria-label="Student Tabs" id="student-profile-tabs">
        @foreach($tabs as $tabKey => $tab)
            <button 
                type="button"
                id="tab-btn-{{ $tabKey }}"
                data-tab="{{ $tabKey }}"
                data-profile-tab="{{ $tabKey }}"
                onclick="switchStudentTab('{{ $tabKey }}')"
                class="student-tab-button group inline-flex items-center gap-2 py-3 px-4 text-xs font-semibold border-b-2 transition-all duration-150 {{ $tabKey === $activeTab ? 'border-[#0A7EA4] text-[#0A7EA4]' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300' }}"
                aria-selected="{{ $tabKey === $activeTab ? 'true' : 'false' }}"
                role="tab"
            >
                <span class="tab-icon transition-colors {{ $tabKey === $activeTab ? 'text-[#0A7EA4]' : 'text-slate-400 group-hover:text-slate-600' }}">
                    {!! $tab['icon'] !!}
                </span>
                <span>{{ $tab['label'] }}</span>
            </button>
        @endforeach
    </nav>
</div>
