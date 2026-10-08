@props(['student', 'options' => []])

@php
    $hasPhoto = $student->photo && \Illuminate\Support\Facades\Storage::disk('public')->exists($student->photo);
    $initials = mb_strtoupper(mb_substr($student->first_name ?? '', 0, 1) . mb_substr($student->last_name ?? '', 0, 1)) ?: 'ST';
    
    $dobText = $student->date_of_birth 
        ? $student->date_of_birth->format('d-m-Y') . ($student->age !== null ? ' (' . $student->age . ' yrs)' : '') 
        : null;

    $admissionDateText = $student->admission_date 
        ? $student->admission_date->format('d-m-Y') 
        : null;

    $className = $student->schoolClass?->name ?? ($options['class_id'][$student->class_id] ?? null);
    $sectionName = $student->section?->name ?? ($options['section_id'][$student->section_id] ?? null);
    $sessionName = $student->academicSession?->name ?? ($student->session?->name ?? ($options['academic_session_id'][$student->academic_session_id] ?? null));
@endphp

<div class="bg-white rounded-2xl border border-slate-200/90 shadow-[0_4px_20px_-4px_rgba(10,126,164,0.07)] overflow-hidden mb-6">
    <div class="grid grid-cols-1 lg:grid-cols-12">
        <!-- Left Profile Identity Column (4 cols) -->
        <div class="lg:col-span-4 p-6 sm:p-7 flex flex-col items-center justify-center text-center lg:border-r border-slate-100 bg-gradient-to-b from-[#f0f9fb]/40 to-white">
            <div class="relative mb-4">
                @if($hasPhoto)
                    <img 
                        src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($student->photo) }}" 
                        alt="{{ $student->full_name }}" 
                        class="w-24 h-24 rounded-full object-cover shadow-sm ring-4 ring-[#e8f7fa] border border-[#0A7EA4]/20"
                    >
                @else
                    <div class="w-24 h-24 rounded-full bg-[#e8f7fa] text-[#0A7EA4] ring-4 ring-[#f0f9fb] border border-[#0A7EA4]/20 flex items-center justify-center text-2xl font-bold tracking-tight shadow-sm select-none">
                        {{ $initials }}
                    </div>
                @endif
            </div>

            <h2 class="text-xl sm:text-2xl font-bold text-slate-800 tracking-tight mb-1">
                {{ $student->full_name ?: ($student->first_name . ' ' . $student->last_name) }}
            </h2>

            <div class="flex items-center gap-2 text-xs font-semibold mb-1">
                <span class="text-slate-500">Admission No:</span>
                <span class="text-[#0A7EA4] bg-[#e8f7fa] px-2 py-0.5 rounded font-mono">{{ $student->admission_no }}</span>
            </div>

            <div class="text-xs text-slate-600 font-medium mb-6">
                <span class="font-semibold text-slate-700">Roll No:</span> {{ $student->roll_number }}
            </div>

            <!-- Action buttons: Red outline Change Status + Filled teal Edit -->
            <div class="flex items-center gap-2.5 w-full max-w-xs justify-center pt-1">
                @can('updateStatus', $student)
                    <!-- Change Status Dropdown / Modal Trigger -->
                    <div class="relative" x-data="{ open: false }">
                        <button 
                            type="button" 
                            id="change-status-btn"
                            onclick="openStatusModal()" 
                            class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2 text-xs font-semibold rounded-lg border border-rose-300 text-rose-600 bg-white hover:bg-rose-50 hover:border-rose-400 focus:outline-none focus:ring-2 focus:ring-rose-200 transition-all shadow-sm"
                            aria-haspopup="dialog"
                        >
                            <svg class="w-3.5 h-3.5 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                            <span>Change Status</span>
                        </button>
                    </div>
                @endcan

                @can('update', $student)
                    <a 
                        href="{{ route('students.edit', $student) }}" 
                        class="inline-flex items-center justify-center gap-1.5 px-4 py-2 text-xs font-semibold rounded-lg text-white bg-[#0A7EA4] hover:bg-[#086987] focus:outline-none focus:ring-2 focus:ring-[#0A7EA4]/40 transition-all shadow-sm"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                        <span>Edit</span>
                    </a>
                @endcan
            </div>
        </div>

        <!-- Right Personal Info Column (8 cols) -->
        <div class="lg:col-span-8 p-6 sm:p-7 flex flex-col justify-between">
            <div>
                <!-- Top Header: Heading + Status Pill -->
                <div class="flex items-center justify-between pb-4 mb-5 border-b border-slate-100">
                    <h3 class="text-base font-bold text-slate-800 tracking-tight flex items-center gap-2">
                        <svg class="w-4 h-4 text-[#0A7EA4]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                        <span>Personal Info</span>
                    </h3>
                    <x-student.status-pill :status="$student->status" />
                </div>

                <!-- Label : Value Info Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-3.5 text-xs">
                    <!-- Class -->
                    <div class="flex items-baseline gap-2">
                        <span class="font-bold text-slate-800 shrink-0 w-28">Class</span>
                        <span class="text-slate-400 font-medium select-none">:</span>
                        <span class="text-slate-600 truncate flex-1 font-medium">
                            {{ $className ?: 'Unassigned' }}
                        </span>
                    </div>

                    <!-- Section -->
                    <div class="flex items-baseline gap-2">
                        <span class="font-bold text-slate-800 shrink-0 w-28">Section</span>
                        <span class="text-slate-400 font-medium select-none">:</span>
                        <span class="text-slate-600 truncate flex-1 font-medium">
                            {{ $sectionName ?: 'Unassigned' }}
                        </span>
                    </div>

                    <!-- Roll No -->
                    <div class="flex items-baseline gap-2">
                        <span class="font-bold text-slate-800 shrink-0 w-28">Roll No</span>
                        <span class="text-slate-400 font-medium select-none">:</span>
                        <span class="text-slate-600 truncate flex-1 font-medium">
                            {{ $student->roll_number }}
                        </span>
                    </div>

                    <!-- Gender -->
                    <div class="flex items-baseline gap-2">
                        <span class="font-bold text-slate-800 shrink-0 w-28">Gender</span>
                        <span class="text-slate-400 font-medium select-none">:</span>
                        <span class="text-slate-600 truncate flex-1 font-medium">
                            {{ $student->gender }}
                        </span>
                    </div>

                    <!-- Date Of Birth -->
                    <div class="flex items-baseline gap-2">
                        <span class="font-bold text-slate-800 shrink-0 w-28">Date Of Birth</span>
                        <span class="text-slate-400 font-medium select-none">:</span>
                        <span class="text-slate-600 truncate flex-1 font-medium">
                            {{ $dobText ?: 'Not provided' }}
                        </span>
                    </div>

                    <!-- B-form Number -->
                    <div class="flex items-baseline gap-2">
                        <span class="font-bold text-slate-800 shrink-0 w-28">B-form Number</span>
                        <span class="text-slate-400 font-medium select-none">:</span>
                        <span class="text-slate-600 truncate flex-1 font-medium font-mono text-[11px]">
                            {{ $student->b_form_number ?: 'Not provided' }}
                        </span>
                    </div>

                    <!-- Academic Year / Session -->
                    <div class="flex items-baseline gap-2">
                        <span class="font-bold text-slate-800 shrink-0 w-28">Academic Year</span>
                        <span class="text-slate-400 font-medium select-none">:</span>
                        <span class="text-slate-600 truncate flex-1 font-medium">
                            {{ $sessionName ?: 'Unassigned' }}
                        </span>
                    </div>

                    <!-- Phone Number (Teal) -->
                    <div class="flex items-baseline gap-2">
                        <span class="font-bold text-slate-800 shrink-0 w-28">Phone Number</span>
                        <span class="text-slate-400 font-medium select-none">:</span>
                        <span class="text-slate-600 truncate flex-1 font-medium">
                            @if($student->phone)
                                <a href="tel:{{ $student->phone }}" class="text-[#0A7EA4] hover:underline font-semibold flex items-center gap-1">
                                    <span>{{ $student->phone }}</span>
                                </a>
                            @else
                                <span class="text-slate-400 italic font-normal">Not provided</span>
                            @endif
                        </span>
                    </div>

                    <!-- Admission Date -->
                    <div class="flex items-baseline gap-2 sm:col-span-2">
                        <span class="font-bold text-slate-800 shrink-0 w-28">Admission Date</span>
                        <span class="text-slate-400 font-medium select-none">:</span>
                        <span class="text-slate-600 truncate flex-1 font-medium">
                            {{ $admissionDateText ?: 'Not provided' }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Profile card footer badge / helper note -->
            <div class="mt-5 pt-3 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-500">
                <span class="flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-[#0A7EA4]"></span>
                    <span>Student Profile &bull; Academic Record</span>
                </span>
                <span class="font-mono text-slate-400">ID: #{{ $student->id }}</span>
            </div>
        </div>
    </div>
</div>
