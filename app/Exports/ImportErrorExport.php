<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;

class ImportErrorExport extends StringValueBinder implements FromArray, WithHeadings, WithCustomValueBinder
{
    public function __construct(private array $errors) {}
    public function headings(): array { return ['Row', 'Errors']; }
    public function array(): array { return array_map(fn ($error) => [$error['row'], implode('; ', $error['errors'])], $this->errors); }
}
