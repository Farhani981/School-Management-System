<div class="student-table-scroll"><table class="student-table library-loan-table">
    <thead><tr><th>S.L</th><th>Member</th><th>Book / Copy</th><th>Issue Date</th><th>Due Date</th><th>Return Date</th><th>Days Late</th><th>Status</th><th>Action</th></tr></thead>
    <tbody>
    @forelse($loans as $loan)
        <tr>
            <td>{{ str_pad($loans->firstItem() + $loop->index, 2, '0', STR_PAD_LEFT) }}</td>
            <td><a href="{{ route('library.members.show', $loan->member) }}">{{ $loan->member->name }}</a><span class="library-member-reference">{{ $loan->member->member_id }}</span></td>
            <td><a href="{{ route('library.borrowing.show', $loan) }}">{{ $loan->book->title }}</a><span class="library-member-reference">{{ $loan->copy->accession_number }}</span></td>
            <td>{{ $loan->issue_date->format('d M Y') }}</td><td>{{ $loan->due_date->format('d M Y') }}</td><td>{{ $loan->return_date?->format('d M Y') ?? 'Not returned' }}</td>
            <td>{{ $loan->days_late }}</td><td><span class="library-loan-status status-{{ strtolower($loan->display_status) }}">{{ $loan->display_status }}</span></td>
            <td><details class="library-row-menu"><summary aria-label="Actions for loan {{ $loan->id }}">&#8942;</summary><div>
                <a href="{{ route('library.borrowing.show', $loan) }}">Loan Details</a>
                @can('receive', $loan) @if(!$loan->return_date)<a href="{{ route('library.borrowing.return', $loan) }}">Receive Return</a>@endif @endcan
            </div></details></td>
        </tr>
    @empty
        <tr><td colspan="9"><div class="students-empty"><h3>No borrowing records found</h3><p>Issued and returned books will appear here.</p></div></td></tr>
    @endforelse
    </tbody>
</table></div>
<div class="student-pagination">{{ $loans->links() }}<span>{{ $loans->total() }} records</span></div>
