<section class="dashboard-reports" aria-labelledby="dashboard-reports-title">
    <div class="dashboard-reports-heading"><div><span class="reports-eyebrow">SCHOOL INSIGHTS</span><h2 id="dashboard-reports-title">Reports Center</h2><p>Your school's attendance, finances and academic progress in one place.</p></div><span class="reports-center-label"><x-icon name="clipboard" size="16" /> Admin reports</span></div>
    <div class="dashboard-report-cards">
        @foreach([
            ['attendance.reports.index','Attendance Reports','Daily summaries, monthly registers and student attendance percentages.','calendar','teal','View attendance'],
            ['fees.reports.index','Fee Reports','Collections, outstanding balances, payment methods and receipt ledger.','wallet','blue','View fee reports'],
            ['exams.index','Exam Report Cards','Select an exam to review student results, grades and individual report cards.','clipboard','purple','Select an exam'],
            ['teachers.payroll.index','Payroll Reports','Review monthly salaries, deductions and advances, then export the payroll summary.','teacher','amber','View payroll']
        ] as [$reportRoute,$label,$description,$icon,$tone,$action])
            <a class="dashboard-report-card report-tone-{{ $tone }}" href="{{ route($reportRoute) }}">
                <span class="dashboard-report-icon"><x-icon :name="$icon" size="23" /></span>
                <h3>{{ $label }}</h3><p>{{ $description }}</p>
                <span class="dashboard-report-action">{{ $action }} <span aria-hidden="true">&rarr;</span></span>
            </a>
        @endforeach
    </div>
</section>
