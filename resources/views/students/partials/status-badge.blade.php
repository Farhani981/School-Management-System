@php($statusTone = match($student->status) { 'Active' => 'active', 'Left', 'Left School' => 'left', 'Passed out', 'Graduated' => 'passed', default => 'inactive' })
<span class="directory-status directory-status-{{ $statusTone }}">{{ $student->status }}</span>
