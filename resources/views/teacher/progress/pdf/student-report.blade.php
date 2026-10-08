<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Progress Report - {{ $student->full_name }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 15mm 15mm 15mm 15mm;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #0f2a33;
            font-size: 11px;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-bottom: 2px solid #0b7a96;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }
        .school-title {
            font-size: 18px;
            font-weight: bold;
            color: #0b7a96;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: -0.3px;
        }
        .report-subtitle {
            font-size: 12px;
            font-weight: 600;
            color: #5d7680;
            margin-top: 2px;
        }
        .meta-right {
            text-align: right;
            font-size: 10px;
            color: #5d7680;
        }
        .student-box {
            background-color: #f2f7f9;
            border: 1px solid #e1e9ec;
            border-radius: 6px;
            padding: 10px 14px;
            margin-bottom: 12px;
        }
        .student-table {
            width: 100%;
        }
        .student-name {
            font-size: 15px;
            font-weight: bold;
            color: #0f2a33;
        }
        .badge {
            display: inline-block;
            padding: 2px 7px;
            font-size: 9px;
            font-weight: bold;
            border-radius: 99px;
            text-transform: uppercase;
        }
        .badge-on-track { background-color: #dcfce7; color: #15803d; }
        .badge-needs-attention { background-color: #ffedd5; color: #c2410c; }
        .badge-at-risk { background-color: #fee2e2; color: #b91c1c; }

        /* KPI Strip */
        .kpi-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            background: #ffffff;
            border: 1px solid #e1e9ec;
            border-radius: 6px;
        }
        .kpi-table td {
            width: 20%;
            text-align: center;
            padding: 8px 6px;
            border-right: 1px solid #e1e9ec;
        }
        .kpi-table td:last-child {
            border-right: none;
        }
        .kpi-label {
            font-size: 8.5px;
            text-transform: uppercase;
            color: #5d7680;
            font-weight: bold;
            display: block;
            margin-bottom: 3px;
        }
        .kpi-val {
            font-size: 14px;
            font-weight: bold;
            color: #0b7a96;
        }

        /* Two column layout */
        .columns-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        .col-half {
            width: 48%;
            vertical-align: top;
        }
        .col-gutter {
            width: 4%;
        }
        .section-header {
            font-size: 11px;
            font-weight: bold;
            color: #0f2a33;
            border-bottom: 1.5px solid #e1e9ec;
            padding-bottom: 4px;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.2px;
        }

        /* Tables */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9.5px;
        }
        .data-table th {
            background-color: #f2f7f9;
            color: #0f2a33;
            font-weight: bold;
            text-align: left;
            padding: 4px 6px;
            border-bottom: 1px solid #e1e9ec;
        }
        .data-table td {
            padding: 4px 6px;
            border-bottom: 1px solid #f0f4f6;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }

        /* Comments Box */
        .comment-box {
            background-color: #fcfdfe;
            border: 1px dashed #0b7a96;
            border-radius: 6px;
            padding: 8px 12px;
            margin-top: 10px;
            font-size: 10px;
        }

        /* Footer */
        .footer-table {
            width: 100%;
            margin-top: 18px;
            border-top: 1px solid #e1e9ec;
            padding-top: 14px;
            font-size: 9px;
            color: #5d7680;
        }
        .sig-line {
            width: 140px;
            border-top: 1px solid #0f2a33;
            margin-top: 25px;
            text-align: center;
            font-size: 9px;
        }
    </style>
</head>
<body>

    <!-- Header -->
    <table class="header-table">
        <tr>
            <td>
                <div class="school-title">School Management System</div>
                <div class="report-subtitle">Student Academic &amp; Attendance Progress Report</div>
            </td>
            <td class="meta-right">
                <div><strong>Generated Date:</strong> {{ $date }}</div>
                <div><strong>Teacher:</strong> {{ $teacher->name }}</div>
            </td>
        </tr>
    </table>

    <!-- Student Meta Box -->
    <div class="student-box">
        <table class="student-table">
            <tr>
                <td style="width: 60%;">
                    <span class="student-name">{{ $student->full_name }}</span>
                    @php $stBadge = $data['status']; @endphp
                    &nbsp;
                    <span class="badge @if($stBadge['state'] === 'on_track') badge-on-track @elseif($stBadge['state'] === 'at_risk') badge-at-risk @else badge-needs-attention @endif">
                        {{ $stBadge['label'] }}
                    </span>
                    <div style="font-size: 9.5px; color: #5d7680; margin-top: 3px;">
                        {{ $stBadge['reason'] }}
                    </div>
                </td>
                <td style="width: 40%; text-align: right; font-size: 10px;">
                    <div><strong>Class &amp; Section:</strong> {{ $student->schoolClass?->name }} &middot; {{ $student->section?->name }}</div>
                    <div><strong>Roll No:</strong> #{{ $student->roll_number }} &nbsp;|&nbsp; <strong>Admission:</strong> {{ $student->admission_no }}</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- KPI Summary Row -->
    @php $sum = $data['summary']; @endphp
    <table class="kpi-table">
        <tr>
            <td>
                <span class="kpi-label">Assessments</span>
                <span class="kpi-val">{{ $sum['assessments_count'] }}</span>
            </td>
            <td>
                <span class="kpi-label">Academic Avg</span>
                <span class="kpi-val">{{ $sum['academic_average'] !== null ? $sum['academic_average'].'%' : '–' }}</span>
            </td>
            <td>
                <span class="kpi-label">Passed</span>
                <span class="kpi-val">{{ $sum['passed_count'] }} / {{ $sum['passed_total'] }}</span>
            </td>
            <td>
                <span class="kpi-label">Attendance Rate</span>
                <span class="kpi-val" style="@if($sum['attendance_pct'] !== null && $sum['attendance_pct'] < 75) color: #b91c1c; @endif">
                    {{ $sum['attendance_pct'] !== null ? $sum['attendance_pct'].'%' : '–' }}
                </span>
            </td>
            <td>
                <span class="kpi-label">Class Rank</span>
                <span class="kpi-val">{{ $sum['class_rank'] }}</span>
            </td>
        </tr>
    </table>

    <!-- Two-Column Breakdown: Subject Performance & Attendance Breakdown -->
    <table class="columns-table">
        <tr>
            <!-- Subject Performance -->
            <td class="col-half">
                <div class="section-header">Subject Performance vs Class</div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th class="text-center">Student Avg</th>
                            <th class="text-center">Class Avg</th>
                            <th class="text-right">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($data['trend_chart']['subject_breakdown']['items'] as $subItem)
                            <tr>
                                <td><strong>{{ $subItem['subject_name'] }}</strong></td>
                                <td class="text-center font-bold">
                                    {{ $subItem['student_avg'] !== null ? $subItem['student_avg'].'%' : '–' }}
                                </td>
                                <td class="text-center" style="color: #5d7680;">
                                    {{ $subItem['class_avg'] !== null ? $subItem['class_avg'].'%' : '–' }}
                                </td>
                                <td class="text-right">
                                    @if($subItem['student_avg'] !== null)
                                        @if($subItem['student_avg'] >= 75)
                                            <span style="color: #15803d; font-weight: bold;">Strong</span>
                                        @elseif($subItem['student_avg'] >= 50)
                                            <span style="color: #0b7a96;">Good</span>
                                        @else
                                            <span style="color: #b91c1c; font-weight: bold;">Needs Help</span>
                                        @endif
                                    @else
                                        –
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center">No subject records available.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </td>

            <td class="col-gutter"></td>

            <!-- Attendance Summary -->
            @php $att = $data['attendance']; @endphp
            <td class="col-half">
                <div class="section-header">Attendance Tracking</div>
                <table class="data-table" style="margin-bottom: 8px;">
                    <tr>
                        <td><strong>Present Days</strong></td>
                        <td class="text-right font-bold" style="color: #15803d;">{{ $att['present'] }}</td>
                        <td><strong>Late Days</strong></td>
                        <td class="text-right font-bold">{{ $att['late'] }}</td>
                    </tr>
                    <tr>
                        <td><strong>Absent Days</strong></td>
                        <td class="text-right font-bold" style="color: #b91c1c;">{{ $att['absent'] }}</td>
                        <td><strong>Overall Rate</strong></td>
                        <td class="text-right font-bold">{{ $att['percentage'] !== null ? $att['percentage'].'%' : '–' }}</td>
                    </tr>
                </table>

                @if(!empty($att['recent_absents']))
                    <div style="font-size: 8.5px; font-weight: bold; color: #5d7680; text-transform: uppercase; margin-bottom: 2px;">Recent Absent Records:</div>
                    <ul style="margin: 0; padding-left: 14px; font-size: 8.5px; color: #5d7680;">
                        @foreach($att['recent_absents'] as $rab)
                            <li>{{ \Carbon\Carbon::parse($rab['date'])->format('d M Y') }} &ndash; {{ $rab['remarks'] }}</li>
                        @endforeach
                    </ul>
                @else
                    <div style="font-size: 9px; color: #15803d;">Clean attendance record. Zero absences logged.</div>
                @endif
            </td>
        </tr>
    </table>

    <!-- Recent Assessment History -->
    <div class="section-header">Recent Assessment Record</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Title</th>
                <th>Subject</th>
                <th>Date</th>
                <th class="text-center">Score</th>
                <th class="text-center">Percentage</th>
                <th class="text-center">Grade</th>
                <th class="text-right">Result</th>
            </tr>
        </thead>
        <tbody>
            @forelse(array_slice($data['assessment_history'], 0, 8) as $hist)
                <tr>
                    <td><strong>{{ $hist['title'] }}</strong> <span style="font-size: 8px; color: #5d7680;">({{ $hist['type'] }})</span></td>
                    <td>{{ $hist['subject'] }}</td>
                    <td>{{ \Carbon\Carbon::parse($hist['date'])->format('d M Y') }}</td>
                    <td class="text-center">{{ $hist['score_display'] }}</td>
                    <td class="text-center font-bold">{{ $hist['percentage'] !== null ? $hist['percentage'].'%' : '–' }}</td>
                    <td class="text-center">{{ $hist['grade'] }}</td>
                    <td class="text-right font-bold @if($hist['status'] === 'Pass') style="color:#15803d;" @elseif($hist['status'] === 'Fail') style="color:#b91c1c;" @endif">
                        {{ $hist['status'] }}
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center">No assessments evaluated yet.</td></tr>
            @endforelse
        </tbody>
    </table>

    <!-- Teacher Remarks Box -->
    <div class="comment-box">
        <strong style="color: #0b7a96; text-transform: uppercase; font-size: 9px;">Teacher Remarks &amp; Recommendations:</strong>
        <p style="margin: 3px 0 0 0;">
            {{ $teacherComment ?: 'Student progress has been evaluated according to regular term curriculum standards. Regular parental review of homework and attendance is encouraged.' }}
        </p>
    </div>

    <!-- Footer Signatures -->
    <table class="footer-table">
        <tr>
            <td style="width: 50%;">
                <div class="sig-line">Class Teacher Signature</div>
            </td>
            <td style="width: 50%; text-align: right;">
                <div class="sig-line" style="margin-left: auto;">Principal Signature &amp; Seal</div>
            </td>
        </tr>
    </table>

</body>
</html>
