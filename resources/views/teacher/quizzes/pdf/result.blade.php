<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Result Sheet - {{ $quiz->title }}</title>
    <style>
        @page { margin: 15mm; size: a4 portrait; }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #0f2a33;
            font-size: 11px;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #0b7a96;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .school-title {
            font-size: 18px;
            font-weight: bold;
            color: #0b7a96;
            margin: 0 0 4px 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .report-subtitle {
            font-size: 13px;
            font-weight: 600;
            color: #2b3f46;
            margin: 0 0 6px 0;
        }
        .report-meta {
            font-size: 10px;
            color: #5d7680;
        }
        .summary-boxes {
            width: 100%;
            margin-bottom: 16px;
            border-collapse: collapse;
        }
        .summary-boxes td {
            width: 16.66%;
            background: #f2f7f9;
            border: 1px solid #d1dfe4;
            padding: 8px 6px;
            text-align: center;
        }
        .sum-lbl {
            font-size: 8.5px;
            font-weight: bold;
            color: #5d7680;
            text-transform: uppercase;
            display: block;
            margin-bottom: 3px;
        }
        .sum-val {
            font-size: 13px;
            font-weight: bold;
            color: #0b7a96;
        }
        .result-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .result-table th, .result-table td {
            border: 1px solid #d1dfe4;
            padding: 6px 8px;
            text-align: left;
        }
        .result-table th {
            background: #0b7a96;
            color: #ffffff;
            font-size: 9.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .result-table tr:nth-child(even) td {
            background: #f8fafb;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .badge-pass { color: #15803d; font-weight: bold; }
        .badge-fail { color: #c2410c; font-weight: bold; }
        .badge-other { color: #5d7680; font-style: italic; }
        .footer {
            margin-top: 25px;
            width: 100%;
        }
        .sig-block {
            width: 45%;
            display: inline-block;
            text-align: center;
            border-top: 1px dashed #7a8490;
            padding-top: 5px;
            font-size: 10px;
            color: #5d7680;
        }
        .sig-right {
            float: right;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="school-title">{{ config('app.name', 'School Management System') }}</div>
        <div class="report-subtitle">Assessment Result Sheet: {{ $quiz->title }}</div>
        <div class="report-meta">
            Class: <strong>{{ $quiz->schoolClass->name }} · {{ $quiz->section->name }}</strong> &nbsp;|&nbsp;
            Subject: <strong>{{ $quiz->subject->name }}</strong> &nbsp;|&nbsp;
            Date: <strong>{{ $quiz->date->format('d M Y') }}</strong> &nbsp;|&nbsp;
            Total Marks: <strong>{{ (float) $quiz->total_marks }}</strong> &nbsp;|&nbsp;
            Passing: <strong>{{ (float) $quiz->passing_marks }}</strong> &nbsp;|&nbsp;
            Teacher: <strong>{{ $quiz->teacher?->name }}</strong>
        </div>
    </div>

    <!-- Summary Box -->
    <table class="summary-boxes">
        <tr>
            <td>
                <span class="sum-lbl">Enrolled</span>
                <span class="sum-val">{{ $stats['total_students'] }}</span>
            </td>
            <td>
                <span class="sum-lbl">Appeared</span>
                <span class="sum-val">{{ $stats['appeared_count'] }}</span>
            </td>
            <td>
                <span class="sum-lbl">Average</span>
                <span class="sum-val">{{ $stats['average_marks'] }}</span>
            </td>
            <td>
                <span class="sum-lbl">Highest</span>
                <span class="sum-val">{{ $stats['highest_mark'] }}</span>
            </td>
            <td>
                <span class="sum-lbl">Lowest</span>
                <span class="sum-val">{{ $stats['lowest_mark'] }}</span>
            </td>
            <td>
                <span class="sum-lbl">Pass Rate</span>
                <span class="sum-val">{{ $stats['pass_percentage'] }}%</span>
            </td>
        </tr>
    </table>

    <!-- Result Table -->
    <table class="result-table">
        <thead>
            <tr>
                <th style="width: 40px;" class="text-center">Rank</th>
                <th style="width: 50px;">Roll #</th>
                <th>Student Name</th>
                <th style="width: 80px;" class="text-center">Marks</th>
                <th style="width: 60px;" class="text-center">%</th>
                <th style="width: 50px;" class="text-center">Grade</th>
                <th style="width: 60px;" class="text-center">Status</th>
                <th>Remarks</th>
            </tr>
        </thead>
        <tbody>
        @foreach($rankedStudents as $row)
            <tr>
                <td class="text-center">{{ $row['rank'] }}</td>
                <td>{{ $row['student']?->roll_number ?: '—' }}</td>
                <td><strong>{{ $row['student']?->full_name }}</strong></td>
                <td class="text-center">
                    {{ $row['marks'] !== null ? $row['marks'] : '—' }}
                </td>
                <td class="text-center">
                    {{ $row['percentage'] !== null ? $row['percentage'] . '%' : '—' }}
                </td>
                <td class="text-center"><strong>{{ $row['grade'] }}</strong></td>
                <td class="text-center">
                    @if($row['status'] === 'present')
                        <span class="{{ $row['passed'] ? 'badge-pass' : 'badge-fail' }}">
                            {{ $row['passed'] ? 'PASS' : 'FAIL' }}
                        </span>
                    @else
                        <span class="badge-other">{{ ucfirst($row['status']) }}</span>
                    @endif
                </td>
                <td>{{ $row['remarks'] ?: '—' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <div class="footer">
        <div class="sig-block">
            Teacher's Signature
        </div>
        <div class="sig-block sig-right">
            Principal / Academic Coordinator
        </div>
    </div>
</body>
</html>
