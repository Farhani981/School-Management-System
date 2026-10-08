<tr>
<td><input type="checkbox" name="students[]" value="{{ $student->id }}" form="student-bulk-form" data-student-select aria-label="Select {{ $student->first_name }} {{ $student->last_name }}"></td>
<td class="directory-serial">{{ $students->firstItem() + $loop->index }}</td>
<td><a class="directory-admission" href="{{ route('students.show',$student) }}">{{ $student->admission_no }}</a></td>
<td><div class="directory-person">@include('students.partials.avatar',['directoryAvatar'=>true])<div><a class="student-name" href="{{ route('students.show',$student) }}">{{ $student->first_name }} {{ $student->last_name }}</a><small class="student-secondary">Roll No: {{ $student->roll_number ?: '—' }}</small></div></div></td>
<td>@if($student->schoolClass)<span class="directory-class">{{ $student->schoolClass->name }}@if($student->section) ({{ $student->section->name }})@endif</span>@if(!$student->section)<small class="directory-warning">Section unassigned</small>@endif @else<span class="directory-warning">Unassigned</span>@endif</td>
<td>{{ $student->father_name }}</td><td>{{ $student->phone ?: '—' }}</td><td>{{ $student->gender }}</td>
<td>@include('students.partials.status-badge')</td>
<td><details class="action-dropdown directory-row-menu"><summary aria-label="Actions for {{ $student->first_name }} {{ $student->last_name }}"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="12" cy="19" r="1.8"/></svg></summary><div class="dropdown-menu"><a href="{{ route('students.show',$student) }}">View</a><a href="{{ route('students.edit',$student) }}">Edit</a><a href="{{ route('students.id-card',$student) }}">Print ID Card</a><button type="button" class="action-delete" data-delete-student="{{ route('students.destroy',$student) }}" data-student-name="{{ $student->first_name }} {{ $student->last_name }}">Delete</button></div></details></td>
</tr>
