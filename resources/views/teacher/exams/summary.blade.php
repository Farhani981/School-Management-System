<section class="td-card exams-page" aria-labelledby="teacher-exams-heading" style="margin:20px 0">
    <div class="panel-heading"><div><h2 id="teacher-exams-heading">My Examinations</h2><p>Your assigned published papers and submission status.</p></div><a class="button-secondary" href="{{ route('teacher.exams.index') }}">View My Exams</a></div>
    <div class="exam-toolbar">
        <span class="exam-status">{{ $examPapers->pluck('examSubject.exam_id')->unique()->count() }} assigned exams</span>
        <span class="exam-status">{{ $examPapers->filter(fn($p)=>$p->exam_date->gte(today()))->count() }} upcoming papers</span>
        @foreach(['not_started'=>'Marks pending','draft'=>'Draft','submitted'=>'Submitted','returned'=>'Returned','approved'=>'Approved'] as $status=>$label)
            <a class="exam-status" href="{{ route('teacher.exams.index',['status'=>$status]) }}">{{ $examPapers->where('marks_status',$status)->count() }} {{ $label }}</a>
        @endforeach
        <a href="{{ route('teacher.exams.schedule') }}">My Schedule →</a>
    </div>
    @include('teacher.exams.pending')
    @if($examPapers->isEmpty())<p class="photo-help" style="padding:0 20px 20px">No published exam papers assigned yet. Your papers appear here after the administrator publishes the datesheet.</p>@else
        <div class="student-table-scroll"><table class="student-table"><thead><tr><th>Exam / Subject</th><th>Class / Section</th><th>Paper date</th><th>Marks deadline</th><th>Status</th><th>Action</th></tr></thead><tbody>
            @foreach($examPapers->take(5) as $paper)<tr><td>{{ $paper->examSubject->exam->name }}<small class="student-secondary">{{ $paper->examSubject->subject->name }}</small></td><td>{{ $paper->examSubject->schoolClass->name }} / {{ $paper->section->name }}</td><td>{{ $paper->exam_date->format('d M Y') }}<small class="student-secondary">{{ $paper->time_label }}</small></td><td>{{ $paper->marks_deadline?->format('d M Y, g:i A') ?? 'Not set' }}</td><td><span class="exam-status">{{ $paper->workflow_status }}</span></td><td><a class="button-primary" href="{{ route('teacher.exams.marks.edit',$paper) }}">{{ in_array($paper->marks_status,['not_started','draft','returned']) && !$paper->examSubject->exam->result_published_at ? 'Enter Marks' : 'View Marks' }}</a></td></tr>@endforeach
        </tbody></table></div>
    @endif
</section>
