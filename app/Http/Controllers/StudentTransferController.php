<?php

namespace App\Http\Controllers;

use App\Exports\StudentExport;
use App\Exports\StudentImportTemplate;
use App\Exports\ImportErrorExport;
use App\Imports\StudentImport;
use App\Services\StudentDirectory;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\HeadingRowImport;

class StudentTransferController extends Controller
{
    public function export(Request $request, string $format, StudentDirectory $directory)
    {
        abort_unless(in_array($format, ['xlsx', 'pdf']), 404);
        $filters = $directory->filters($request);
        $options = StudentRecordsController::options();
        if ($format === 'xlsx') return Excel::download(new StudentExport($filters, $options), 'students.xlsx');
        return Pdf::loadView('students.pdf.directory', ['students' => $directory->query($filters)->get(), 'options' => $options])->setPaper('a4', 'landscape')->download('students.pdf');
    }

    public function index()
    {
        return view('students.import', ['options' => StudentRecordsController::options()]);
    }

    public function template() { return Excel::download(new StudentImportTemplate(StudentRecordsController::options()), 'students-template.xlsx'); }

    public function import(Request $request)
    {
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls', 'max:5120']]);
        try {
            $headings = (new HeadingRowImport)->toArray($request->file('file'))[0][0] ?? [];
        } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $exception) {
            return back()->withErrors(['file' => 'The workbook could not be read. Upload a valid Excel workbook based on the template.']);
        }
        $required = ['first_name', 'father_name', 'date_of_birth', 'gender', 'phone', 'address', 'admission_date', 'roll_number', 'status'];
        if ($missing = array_diff($required, $headings)) return back()->withErrors(['file' => 'Missing columns: '.implode(', ', $missing)]);
        $import = new StudentImport;
        Excel::import(new \App\Imports\StudentWorkbookImport($import), $request->file('file'));
        $request->session()->put('student_import_report', ['imported' => $import->imported, 'errors' => $import->errors]);
        return redirect()->route('students.import')->with('success', $import->imported.' students imported; '.count($import->errors).' rows skipped.');
    }

    public function errors(Request $request)
    {
        abort_unless($request->session()->has('student_import_report'), 404);
        return Excel::download(new ImportErrorExport($request->session()->get('student_import_report.errors', [])), 'student-import-errors.xlsx');
    }
}
