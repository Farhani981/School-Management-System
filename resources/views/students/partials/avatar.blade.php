<span class="student-avatar @if($directoryAvatar ?? false) directory-avatar avatar-tone-{{ $student->id % 6 }} @endif">
    @php($hasPhoto = $student->photo && \Illuminate\Support\Facades\Storage::disk('public')->exists($student->photo))
    @if($hasPhoto)<img data-student-avatar src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($student->photo) }}" alt="{{ $student->first_name }} {{ $student->last_name }}" loading="lazy">@endif
    <span @if($hasPhoto) hidden @endif aria-label="{{ $student->first_name }} {{ $student->last_name }} initials">{{ mb_strtoupper(mb_substr($student->first_name, 0, 1).mb_substr($student->last_name ?? '', 0, 1)) }}</span>
</span>
