<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class StudentWorkbookImport implements WithMultipleSheets
{
    public function __construct(public StudentImport $students) {}
    public function sheets(): array { return [0 => $this->students]; }
}
