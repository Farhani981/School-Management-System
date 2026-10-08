<span class="student-avatar">
@if($teacher->photo && \Illuminate\Support\Facades\Storage::disk('public')->exists($teacher->photo))<img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($teacher->photo) }}" alt="{{ $teacher->name }}" data-teacher-avatar><span hidden>{{ $teacher->initials }}</span>@else<span>{{ $teacher->initials ?: '?' }}</span>@endif
</span>
