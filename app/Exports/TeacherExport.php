<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;

class TeacherExport implements FromCollection, WithHeadings, WithStrictNullComparison
{
    public function __construct(private Collection $teachers) {}

    public function headings(): array
    {
        return ['Employee ID', 'Name', 'CNIC', 'Gender', 'Phone', 'Email', 'Department', 'Designation', 'Joining Date', 'Status'];
    }

    public function collection()
    {
        return $this->teachers->map(fn ($t) => [$t->employee_id, $t->name, $t->cnic, $t->gender, $t->phone, $t->email, $t->department?->name, $t->designation?->name, $t->joining_date->format('Y-m-d'), $t->status]);
    }
}
