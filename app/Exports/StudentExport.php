<?php

namespace App\Exports;

use App\Services\StudentDirectory;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;

class StudentExport extends StringValueBinder implements FromQuery, WithHeadings, WithMapping, WithCustomValueBinder
{
    public function __construct(private array $filters, private array $options) {}
    public function query() { return app(StudentDirectory::class)->query($this->filters); }
    public function headings(): array { return ['Admission No', 'Name', 'Father', 'Phone', 'Date of Birth', 'Admission Date', 'Class', 'Section', 'Session', 'Roll No', 'Status']; }
    public function map($student): array
    {
        return [$student->admission_no, trim($student->first_name.' '.$student->last_name), $student->father_name, $student->phone, $student->date_of_birth?->format('d-m-Y'), $student->admission_date?->format('d-m-Y'), $this->options['class_id'][$student->class_id] ?? '', $this->options['section_id'][$student->section_id] ?? '', $this->options['academic_session_id'][$student->academic_session_id] ?? '', $student->roll_number, $student->status];
    }
}
