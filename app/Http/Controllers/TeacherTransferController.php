<?php

namespace App\Http\Controllers;

use App\Exports\TeacherExport;
use App\Http\Requests\TeacherActionRequest;
use App\Imports\TeacherImport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class TeacherTransferController extends Controller
{
    public function index()
    {
        return view('teachers.import');
    }

    public function store(TeacherActionRequest $request)
    {
        $import = new TeacherImport;
        DB::transaction(fn () => Excel::import($import, $request->file('file')), 3);

        return redirect()->route('teachers.index')->with('success', "Imported {$import->count} teachers.");
    }

    public function export(Request $request, string $format)
    {
        abort_unless(in_array($format, ['xlsx', 'pdf']), 404);
        $teachers = TeacherController::directory($request)->get();

        return $format === 'xlsx' ? Excel::download(new TeacherExport($teachers), 'teachers.xlsx') : Pdf::loadView('teachers.pdf.directory', compact('teachers'))->setPaper('a4', 'landscape')->download('teachers.pdf');
    }

    public function template()
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['name', 'cnic', 'gender', 'phone', 'email', 'department', 'designation', 'joining_date', 'status', 'basic_salary', 'house_allowance', 'medical_allowance', 'transport_allowance', 'other_allowance', 'fixed_deductions', 'bank_name', 'account_no', 'qualification', 'experience']);
            fclose($out);
        }, 'teachers-template.csv', ['Content-Type' => 'text/csv']);
    }
}
