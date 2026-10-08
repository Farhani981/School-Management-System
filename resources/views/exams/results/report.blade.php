<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $report['student_name'] }} — Report Card</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#eef1f5;color:#181818;font-family:"Times New Roman",DejaVu Serif,serif;font-size:12px;line-height:1.45}.report{width:190mm;max-width:calc(100% - 32px);margin:22px auto;background:white;border:1px solid #9baac0;padding:12mm;min-height:265mm}.report-head{text-align:center;margin-bottom:22px}.session{font-style:italic;font-size:12px;margin:0 0 8px}.report-head h1{font-size:22px;letter-spacing:.6px;line-height:1.35;margin:0;font-weight:bold}.exam-name{font-size:11px;color:#5a6472;margin:7px 0 0}.student-info{border-collapse:collapse;width:100%;margin-bottom:17px}.student-info td{padding:4px 0;vertical-align:top}.student-info .label{width:135px;font-weight:bold;font-size:11px}.logo-cell{width:100px;text-align:right;vertical-align:middle!important}.school-logo{width:85px;height:85px;object-fit:contain}.marks,.grading{width:100%;border-collapse:collapse;table-layout:fixed;font-size:12px}.marks th,.marks td,.grading th,.grading td{border:1px solid #b9cbe0;padding:4px 6px;line-height:1.3}.marks th{background:#f4f7fa;font-weight:bold}.marks .number{width:10%}.marks .subject{width:26%;text-align:left}.marks .maximum{width:21%}.marks .obtained{width:18%}.marks .percentage{width:14%}.marks .grade{width:11%}.marks td{text-align:center}.marks td.subject-name{text-align:left;overflow-wrap:break-word}.marks tfoot td{background:#f5f7fa;font-weight:bold}.marks tfoot .total-label{text-align:left}.grading{margin:13px 0 10px;font-size:11px}.grading th{background:#f4f7fa}.grading td{text-align:center;padding:4px 3px}.remarks{margin-top:12px;min-height:95px;page-break-inside:avoid}.remarks h2{font-size:11px;margin:0 0 9px;font-weight:bold}.remarks p{margin:0;line-height:1.7;white-space:pre-line;overflow-wrap:break-word}.signatures{width:100%;border-collapse:collapse;margin-top:45px;page-break-inside:avoid}.signatures td{width:50%;vertical-align:bottom}.signature{width:125px;text-align:center;font-size:11px}.signature-head{margin-left:auto}.signature-space{height:30px}.signature-line{border-top:1px solid #8d9aaf;padding-top:7px}.pending{padding:9px 12px;background:#fff8e7;border:1px solid #e6d4a1;font-size:11px;margin:0 0 16px}.toolbar{width:190mm;max-width:calc(100% - 32px);margin:20px auto 0;display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;font-family:Arial,sans-serif}.toolbar a,.toolbar button{display:inline-block;font:inherit;font-size:13px;padding:10px 15px;border:1px solid #cbd8e2;border-radius:7px;background:#fff;color:#235775;text-decoration:none;cursor:pointer}.toolbar .primary{background:#176a8d;color:white;border-color:#176a8d}.toolbar-actions{display:flex;gap:8px}.toolbar a:focus-visible,.toolbar button:focus-visible{outline:2px solid #176a8d;outline-offset:3px}thead{display:table-header-group}tr{page-break-inside:avoid}@page{size:A4 portrait;margin:10mm}@media print{body{background:#fff}.no-print{display:none!important}.report{width:100%;max-width:none;min-height:265mm;margin:0;padding:12mm;border:1px solid #9baac0}}@media screen and (max-width:600px){.report{padding:20px 14px;min-height:0;margin:16px auto}.report-head h1{font-size:19px}.student-info .label{width:105px}.school-logo{width:65px;height:65px}.logo-cell{width:70px}.marks{font-size:10px}.marks th,.marks td{padding:4px 3px}.grading{font-size:9px}.signature{width:100px}.signatures{margin-top:30px}}
.pdf-document .report{width:auto;max-width:none;margin:0;min-height:235mm;padding:12mm}
</style>
</head>
<body class="{{ $pdf ? 'pdf-document' : '' }}">
@php
    $student = $report['student'];
    $gradeScales = \App\Models\GradeScale::orderByDesc('min_percent')->get();
    $formatMarks = fn ($value) => rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    $logo = config('school.logo');
    $logoPath = null;
    if ($logo && str_starts_with($logo, '/') && !str_starts_with($logo, '//')) {
        $resolvedLogo = realpath(public_path(ltrim($logo, '/')));
        $publicRoot = realpath(public_path());
        $storageRoot = realpath(storage_path('app/public'));
        if ($resolvedLogo && is_file($resolvedLogo) && (($publicRoot && str_starts_with($resolvedLogo, $publicRoot.DIRECTORY_SEPARATOR)) || ($storageRoot && str_starts_with($resolvedLogo, $storageRoot.DIRECTORY_SEPARATOR)))) {
            $logoPath = $resolvedLogo;
        }
    }
@endphp
@if(!$pdf)
<nav class="toolbar no-print" aria-label="Report actions"><a href="{{ route('exams.results.index',$exam) }}">&larr; Student Results</a><div class="toolbar-actions"><button type="button" onclick="window.print()">Print</button><a class="primary" href="{{ route('exams.results.show',[$exam,$student,'download'=>'pdf']) }}">Download PDF</a></div></nav>
@endif
<main class="report">
<header class="report-head"><p class="session">Report card for the session {{ $exam->session->name }}</p><h1>{{ config('school.name', 'School Management System') }}</h1><p class="exam-name">{{ $exam->name }}</p></header>
@if(!$report['complete'])<p class="pending">Provisional report: subject marks are still awaiting submission. Final totals and status appear when all subject marks are submitted.</p>@endif
<table class="student-info"><tbody>
<tr><td class="label">Class:</td><td>{{ $report['class_name'] }}@if($report['section_name']) / {{ $report['section_name'] }}@endif</td>@if($logoPath)<td class="logo-cell" rowspan="5"><img class="school-logo" src="{{ $pdf ? $logoPath : asset(ltrim($logo, '/')) }}" alt="School logo"></td>@endif</tr>
<tr><td class="label">Roll No:</td><td>{{ $report['roll_number'] ?? '—' }}</td></tr>
<tr><td class="label">Registration No:</td><td>{{ $report['admission_no'] }}</td></tr>
<tr><td class="label">Name:</td><td>{{ $report['student_name'] }}</td></tr>
<tr><td class="label">Parent/Guardian:</td><td>{{ $student->guardian_name ?: ($student->father_name ?: ($student->mother_name ?: '—')) }}</td></tr>
</tbody></table>
<table class="marks"><thead><tr><th class="number" scope="col">Sr No</th><th class="subject" scope="col">Subject</th><th class="maximum" scope="col">Total Marks</th><th class="obtained" scope="col">Obtained</th><th class="percentage" scope="col">%</th><th class="grade" scope="col">Grade</th></tr></thead><tbody>
@foreach($report['rows'] as $row)
@php
    $subject = $row['paper']->examSubject;
    $result = $row['result'];
    $absent = $result && $result->attendance_status === 'absent';
    $marks = $row['ready'] && !$absent ? $result->marks_obtained : null;
    $subjectPercentage = $row['ready'] && (float) $subject->total_marks > 0 ? round((float) ($marks ?? 0) / (float) $subject->total_marks * 100, 1) : null;
@endphp
<tr><td>{{ $loop->iteration }}</td><td class="subject-name">{{ $subject->subject->name }}</td><td>{{ $formatMarks($subject->total_marks) }}</td><td>{{ !$row['ready'] ? 'Pending' : ($absent ? 'ABS' : $formatMarks($marks)) }}</td><td>{{ $subjectPercentage === null ? '—' : number_format($subjectPercentage, 1).'%' }}</td><td>{{ $row['grade'] ?? '—' }}</td></tr>
@endforeach
</tbody><tfoot><tr><td></td><td class="total-label">Grand Total</td><td>{{ $formatMarks($report['maximum']) }}</td><td>{{ $report['complete'] ? $formatMarks($report['total']) : 'Pending' }}</td><td>{{ $report['complete'] ? number_format($report['percentage'], 1).'%' : '—' }}</td><td>{{ $report['grade'] ?? '—' }}</td></tr><tr><td></td><td class="total-label" colspan="4">Status</td><td>{{ $report['status'] }}</td></tr></tfoot></table>
<table class="grading"><thead><tr><th colspan="4">Grading Key</th></tr></thead><tbody>
@forelse($gradeScales->chunk(4) as $grades)<tr>@foreach($grades as $scale)<td>{{ $scale->grade }} = ({{ $formatMarks($scale->min_percent) }} - {{ $formatMarks($scale->max_percent) }} )%</td>@endforeach @for($empty=$grades->count();$empty<4;$empty++)<td></td>@endfor</tr>@empty<tr><td colspan="4">Grading scale has not been configured.</td></tr>@endforelse
</tbody></table>
<section class="remarks"><h2>Class Teacher Remarks:</h2><p>{{ $report['remarks'] ?: '—' }}</p></section>
<table class="signatures"><tr><td><div class="signature"><div class="signature-space"></div><div class="signature-line">Class Teacher</div></div></td><td><div class="signature signature-head"><div class="signature-space"></div><div class="signature-line">Head</div></div></td></tr></table>
</main>
</body>
</html>
