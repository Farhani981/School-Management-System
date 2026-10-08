<div class="teacher-leave-summary">
@foreach(['total'=>['Total requests',$leaveCounts->sum(),'calendar'],'approved'=>['Approved',$leaveCounts->get('approved',0),'check'],'pending'=>['Pending',$leaveCounts->get('pending',0),'clock'],'rejected'=>['Rejected',$leaveCounts->get('rejected',0),'clipboard']] as $tone=>[$label,$count,$icon])
<div class="teacher-leave-stat teacher-leave-stat-{{ $tone }}"><span class="teacher-leave-stat-icon"><x-icon :name="$icon" size="21"/></span><div><strong>{{ $count }}</strong><span>{{ $label }}</span></div></div>
@endforeach
</div>
<section class="teacher-leave-history" aria-labelledby="teacher-leave-history-title">
<div class="teacher-leave-history-heading"><div><h2 id="teacher-leave-history-title">Leave History</h2><p>Track your requests and the school's decisions.</p></div><span>{{ $leaves->total() }} requests</span></div>
<div class="teacher-leave-list">
@forelse($leaves as $leave)
<article class="teacher-leave-request">
<div class="teacher-leave-request-heading"><div class="teacher-leave-type"><span class="teacher-leave-type-icon"><x-icon name="calendar" size="21"/></span><div><h3>{{ $leave->leaveType?->name ?? 'Leave' }}</h3><p>Request #{{ $leave->id }} · Submitted {{ $leave->created_at?->format('d M Y') }}</p></div></div><span class="teacher-leave-status teacher-leave-status-{{ strtolower($leave->status) }}">{{ $leave->status }}</span></div>
<dl class="teacher-leave-details"><div><dt>From</dt><dd>{{ ($leave->from_date ?? $leave->start_date)?->format('d M Y') ?? 'Not set' }}</dd></div><div><dt>To</dt><dd>{{ ($leave->to_date ?? $leave->end_date)?->format('d M Y') ?? 'Not set' }}</dd></div><div><dt>Duration</dt><dd>{{ $leave->days !== null ? rtrim(rtrim($leave->days,'0'),'.') : ($leave->half_day ? '0.5' : ((int) $leave->start_date->diffInDays($leave->end_date) + 1)) }} days @if($leave->half_day)<span class="teacher-leave-half">Half day</span>@endif</dd></div></dl>
@if($leave->reason)<div class="teacher-leave-note"><strong>Reason</strong><p>{{ $leave->reason }}</p></div>@endif
@if($leave->admin_remark ?: $leave->remarks)<div class="teacher-leave-note teacher-leave-review"><strong>School remarks</strong><p>{{ $leave->admin_remark ?: $leave->remarks }}</p></div>@endif
@if($leave->decided_at ?? $leave->reviewed_at)<p class="teacher-leave-reviewed">Reviewed {{ ($leave->decided_at ?? $leave->reviewed_at)->format('d M Y, g:i A') }}</p>@endif
</article>
@empty<div class="teacher-leave-empty"><x-icon name="calendar" size="32"/><h3>No leave requests yet</h3><p>Your leave dates and approval history will appear here once a request is recorded.</p></div>@endforelse
</div><div class="student-pagination">{{ $leaves->links() }}</div>
</section>
