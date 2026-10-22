<section class="dashboard-reports" aria-labelledby="dashboard-reports-title">
    <div class="dashboard-reports-heading">
        <div class="reports-title-wrap">
            <span class="reports-header-icon"><x-icon name="clipboard" size="18" /></span>
            <div>
                <h2 id="dashboard-reports-title">Reports Center</h2>
                <p>Access detailed reports for better decision making.</p>
            </div>
        </div>
        <a class="reports-center-label" href="{{ route('attendance.reports.index') }}">
            <span>View All Reports</span>
            <span aria-hidden="true">&rarr;</span>
        </a>
    </div>
    <div class="dashboard-report-cards">
        @foreach([
            ['attendance.reports.index','Attendance Reports','Daily, monthly and custom attendance reports.','calendar','teal','View Reports'],
            ['fees.reports.index','Fee Reports','Collection, outstanding and payment reports.','wallet','blue','View Reports'],
            ['exams.index','Exam Reports','Results, grades and performance reports.','clipboard','purple','View Reports'],
            ['teachers.payroll.index','Payroll Reports','Salaries, deductions and payroll summary.','users','amber','View Reports']
        ] as [$reportRoute,$label,$description,$icon,$tone,$action])
            <a class="dashboard-report-card report-tone-{{ $tone }}" href="{{ route($reportRoute) }}">
                <span class="dashboard-report-icon"><x-icon :name="$icon" size="20" /></span>
                <h3>{{ $label }}</h3>
                <p>{{ $description }}</p>
                <span class="dashboard-report-action">{{ $action }} <span aria-hidden="true">&rarr;</span></span>
            </a>
        @endforeach
    </div>
</section>
