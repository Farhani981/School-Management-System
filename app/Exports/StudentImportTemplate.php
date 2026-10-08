<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;

class StudentImportTemplate extends StringValueBinder implements FromArray, WithHeadings, WithCustomValueBinder
{
    public function __construct(private array $options) {}
    public function headings(): array { return ['first_name', 'last_name', 'father_name', 'date_of_birth', 'gender', 'phone', 'address', 'admission_date', 'class_id', 'section_id', 'academic_session_id', 'roll_number', 'status', 'guardian_name', 'guardian_relationship', 'guardian_phone', 'family_id']; }
    public function array(): array
    {
        return [['Sample', 'Student', 'Sample Father', '10-01-2015', 'Male', '03001234567', 'Sample address', now()->format('d-m-Y'), $this->options['class_id']->keys()->first(), $this->options['section_id']->keys()->first(), $this->options['academic_session_id']->keys()->first(), '1001', 'Active', '', '', '', '']];
    }
}
