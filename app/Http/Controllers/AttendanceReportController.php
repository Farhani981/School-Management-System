<?php

namespace App\Http\Controllers;

use App\Exports\AttendanceReportExport;
use App\Http\Requests\AttendanceReportRequest;
use App\Services\AttendanceReports;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

class AttendanceReportController extends Controller
{
    public function index(AttendanceReportRequest $request, AttendanceReports $service)
    {
        return view('attendance.reports', $service->build($request->validated(), $request->user()));
    }

    public function export(AttendanceReportRequest $request, AttendanceReports $service, string $format)
    {
        abort_unless(in_array($format, ['xlsx', 'pdf']), 404);
        $report = $service->build($request->validated(), $request->user());

        return $format === 'xlsx' ? Excel::download(new AttendanceReportExport($report), 'attendance.xlsx') : Pdf::loadView('attendance.pdf.report', $report)->setPaper('a4', 'landscape')->download('attendance.pdf');
    }
}
