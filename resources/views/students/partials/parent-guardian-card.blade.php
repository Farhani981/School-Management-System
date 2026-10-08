@props(['student'])

@php
    $rows = [
        [
            'relation' => 'Father',
            'name' => $student->father_name,
            'phone' => $student->phone,
            'email' => $student->emergency_contact ?: ($student->email ?: null),
            'avatar_color' => 'bg-amber-100 text-amber-700',
            'avatar_icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>',
        ],
        [
            'relation' => 'Mother',
            'name' => $student->mother_name,
            'phone' => $student->emergency_contact ?: ($student->guardian_phone ?: null),
            'email' => null,
            'avatar_color' => 'bg-sky-100 text-sky-700',
            'avatar_icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>',
        ],
        [
            'relation' => $student->guardian_relationship ? 'Guardian (' . $student->guardian_relationship . ')' : 'Guardian (Father)',
            'name' => $student->guardian_name ?: ($student->father_name ?: null),
            'phone' => $student->guardian_phone ?: ($student->phone ?: null),
            'email' => $student->emergency_contact ?: null,
            'avatar_color' => 'bg-emerald-100 text-emerald-700',
            'avatar_icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>',
        ],
    ];
@endphp

<x-student.detail-card title="Parent / Guardian Detail">
    <x-slot:icon>
        <svg class="w-4 h-4 text-[#0A7EA4]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
    </x-slot:icon>

    <div class="divide-y divide-slate-100">
        @foreach($rows as $person)
            @php
                $digits = preg_replace('/\D/', '', (string) $person['phone']);
                $whatsapp = str_starts_with($digits, '0') ? '92' . substr($digits, 1) : $digits;
            @endphp
            <div class="py-4 first:pt-0 last:pb-0 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <!-- Person identity -->
                <div class="flex items-center gap-3.5 min-w-[220px]">
                    <div class="w-11 h-11 rounded-full {{ $person['avatar_color'] }} flex items-center justify-center shrink-0 shadow-xs border border-white ring-2 ring-slate-100">
                        {!! $person['avatar_icon'] !!}
                    </div>
                    <div>
                        <h4 class="text-xs font-bold text-slate-800">
                            {{ $person['name'] ?: 'Not provided' }}
                        </h4>
                        <span class="text-[11px] text-slate-500 font-medium block mt-0.5">
                            {{ $person['relation'] }}
                        </span>
                    </div>
                </div>

                <!-- Phone number with quick call and WhatsApp actions -->
                <div class="min-w-[200px]">
                    <span class="text-[11px] text-slate-400 font-semibold block mb-0.5">Phone</span>
                    @if($person['phone'])
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-semibold text-slate-700 font-mono">{{ $person['phone'] }}</span>
                            @if($digits)
                                <a 
                                    href="tel:{{ $digits }}" 
                                    class="inline-flex items-center justify-center p-1.5 rounded-md text-[#0A7EA4] bg-[#e8f7fa] hover:bg-[#d5eff5] transition-colors" 
                                    title="Call {{ $person['relation'] }}"
                                    aria-label="Call {{ $person['name'] }}"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                                </a>
                                <a 
                                    href="https://wa.me/{{ $whatsapp }}" 
                                    target="_blank" 
                                    rel="noopener noreferrer" 
                                    class="inline-flex items-center justify-center p-1.5 rounded-md text-emerald-700 bg-emerald-50 hover:bg-emerald-100 transition-colors" 
                                    title="WhatsApp {{ $person['relation'] }}"
                                    aria-label="WhatsApp {{ $person['name'] }}"
                                >
                                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                                </a>
                            @endif
                        </div>
                    @else
                        <span class="text-xs text-slate-400 italic font-normal">Not provided</span>
                    @endif
                </div>

                <!-- Email or CNIC -->
                <div class="min-w-[180px]">
                    <span class="text-[11px] text-slate-400 font-semibold block mb-0.5">Email / CNIC</span>
                    @if($person['email'])
                        <span class="text-xs text-slate-700 font-medium truncate block max-w-xs">{{ $person['email'] }}</span>
                    @else
                        <span class="text-xs text-slate-400 italic font-normal">Not provided</span>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</x-student.detail-card>
