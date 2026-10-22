<div class="panel-heading">
    <div>
        <h2>Student Fee Balances</h2>
        <p>Who has paid, who still owes fees, and exactly how much is remaining.</p>
    </div><a class="button-secondary"
        href="{{ request()->fullUrlWithQuery(['from' => $from, 'to' => $to, 'export' => 'students', 'student_page' => null, 'page' => null]) }}">Export
        Student Balances</a>
</div>
<div class="student-fee-status-cards">
    @foreach (['Paid' => ['Fully Paid', 'green'], 'Partial' => ['Partially Paid', 'amber'], 'Unpaid' => ['Not Paid', 'red']] as $status => [$label, $tone])
        <a class="student-fee-status-card student-fee-tone-{{ $tone }}"
            href="{{ request()->fullUrlWithQuery(['fee_status' => $status, 'student_search' => null, 'student_page' => null, 'export' => null]) }}"><span>{{ $label }}</span><strong>{{ $studentFeeCounts->get($status, 0) }}</strong><small>students
                &rarr;</small></a>
    @endforeach
</div>
<form method="GET" action="{{ route('fees.reports.index') }}" class="student-fee-filters">
    <input type="hidden" name="from" value="{{ $from }}"><input type="hidden" name="to"
        value="{{ $to }}">
    @if (request()->filled('class_id'))
        <input type="hidden" name="class_id" value="{{ request('class_id') }}">
    @endif
    <div class="admission-field"><label for="balance-student-search">Student / Admission no.</label><input
            id="balance-student-search" name="student_search" value="{{ request('student_search') }}"
            placeholder="Search student name or admission number" maxlength="150"></div>
    <div class="admission-field"><label for="balance-status">Payment status</label><select id="balance-status"
            name="fee_status">
            <option value="">All statuses</option>
            @foreach (['Paid' => 'Fully Paid', 'Partial' => 'Partially Paid', 'Unpaid' => 'Not Paid'] as $value => $label)
                <option value="{{ $value }}" @selected(request('fee_status') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <button class="button-primary" type="submit">Search Balances</button>
    @if (request()->filled('student_search') || request()->filled('fee_status'))
        <a class="button-secondary"
            href="{{ request()->fullUrlWithQuery(['student_search' => null, 'fee_status' => null, 'student_page' => null, 'export' => null]) }}">Reset</a>
    @endif
</form>
<p class="fee-report-note">Balances as of {{ today()->format('d M Y') }} across all active vouchers in the selected
    class, including applicable fines. Date filters above apply to collections and the payment ledger. Students without
    issued vouchers are not included.</p>
<div class="student-table-scroll">
    <table class="student-table student-fee-balance-table">
        <thead>
            <tr>
                <th>Student</th>
                <th>Class</th>
                <th>Vouchers</th>
                <th>Total Fee (PKR)</th>
                <th>Paid (PKR)</th>
                <th>Remaining (PKR)</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            @forelse($studentFees as $row)
                <tr>
                    <td><strong>{{ $row['name'] }}</strong><small
                            class="student-secondary">{{ $row['admission_no'] }}</small></td>
                    <td>{{ $row['classes'] }}</td>
                    <td>{{ $row['vouchers'] }}</td>
                    <td class="fee-report-money">{{ number_format($row['total'] / 100, 2) }}</td>
                    <td class="fee-report-money student-fee-paid">{{ number_format($row['paid'] / 100, 2) }}</td>
                    <td class="fee-report-money"><strong
                            class="{{ $row['remaining'] > 0 ? 'student-fee-due' : 'student-fee-paid' }}">{{ number_format($row['remaining'] / 100, 2) }}</strong>
                        @if ($row['overdue'] > 0)
                            <small class="student-fee-overdue">{{ number_format($row['overdue'] / 100, 2) }}
                                overdue</small>
                        @endif
                    </td>
                    <td><span
                            class="student-fee-badge student-fee-badge-{{ strtolower($row['status']) }}">{{ ['Paid' => 'Fully Paid', 'Partial' => 'Partially Paid', 'Unpaid' => 'Not Paid'][$row['status']] }}</span>
                    </td>
                    <td>
                        <details class="action-dropdown fee-balance-row-menu" data-left-row-menu>
                            <summary aria-label="Actions for {{ $row['name'] }}" title="Actions">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="5" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="12" cy="19" r="1.8"/></svg>
                            </summary>
                            <div class="dropdown-menu"><a
                                    href="{{ route('fees.students.history', $row['student_id']) }}">Fee History</a><a
                                    href="{{ route('fees.vouchers.index', ['search' => $row['admission_no']]) }}">View
                                    Vouchers</a></div>
                        </details>
                    </td>
                </tr>
            @empty<tr>
                    <td colspan="8">
                        <div class="fee-report-empty">No student fee balances match your selection.</div>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="student-pagination">{{ $studentFees->links() }}</div>
