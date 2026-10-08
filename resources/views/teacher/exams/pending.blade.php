@if($pendingExamAssignments->isNotEmpty())
    <div class="exam-toolbar" role="status">
        <div>
            <strong>Awaiting datesheet publication</strong>
            <p>The administrator has assigned these subjects to you. Paper dates and Enter Marks will appear after the exam datesheet is saved and published.</p>
            @foreach($pendingExamAssignments as $assignment)
                <p><strong>{{ $assignment->examSubject->exam->name }}</strong> &middot; {{ $assignment->examSubject->subject->name }} &middot; {{ $assignment->examSubject->schoolClass->name }} / {{ $assignment->section->name }} <span class="exam-status">Awaiting datesheet</span></p>
            @endforeach
        </div>
    </div>
@endif
