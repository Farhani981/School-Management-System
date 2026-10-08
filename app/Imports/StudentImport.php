<?php

namespace App\Imports;

use App\Http\Requests\StoreStudentRequest;
use App\Models\Student;
use App\Services\StudentRecords;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Row;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class StudentImport implements OnEachRow, WithHeadingRow, SkipsEmptyRows
{
    public int $imported = 0;
    public array $errors = [];

    public function onRow(Row $row): void
    {
        $data = $row->toArray();
        foreach ($data as $key => $value) $data[$key] = $value === '' ? null : $value;
        foreach (['phone', 'roll_number', 'guardian_phone', 'family_id', 'admission_no'] as $field) {
            if (isset($data[$field])) $data[$field] = (string) $data[$field];
        }
        foreach (['date_of_birth', 'admission_date'] as $field) {
            $value = $data[$field] ?? null;
            if (is_numeric($value)) $data[$field] = Date::excelToDateTimeObject($value)->format('Y-m-d');
            elseif (is_string($value) && preg_match('/^\d{2}-\d{2}-\d{4}$/', $value)) {
                try {
                    $date = \Carbon\Carbon::createFromFormat('!d-m-Y', $value);
                    if ($date->format('d-m-Y') === $value) $data[$field] = $date->format('Y-m-d');
                } catch (\Throwable) {}
            }
        }
        $request = StoreStudentRequest::create('/', 'POST', $data);
        $rules = $request->rules();
        unset($rules['photo'], $rules['documents'], $rules['documents.*']);
        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            $this->errors[] = ['row' => $row->getIndex(), 'errors' => $validator->errors()->all()];
            return;
        }
        try {
            app(StudentRecords::class)->save(new Student, $validator->validated());
            $this->imported++;
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->errors[] = ['row' => $row->getIndex(), 'errors' => array_merge(...array_values($exception->errors()))];
        } catch (QueryException $exception) {
            if (!str_contains(strtolower($exception->getMessage()), 'unique') && !str_contains(strtolower($exception->getMessage()), 'duplicate')) throw $exception;
            $this->errors[] = ['row' => $row->getIndex(), 'errors' => ['Duplicate admission or class roll number.']];
        }
    }
}
