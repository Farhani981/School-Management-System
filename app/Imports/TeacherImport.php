<?php

namespace App\Imports;

use App\Http\Requests\TeacherRequest;
use App\Models\Department;
use App\Models\Designation;
use App\Services\TeacherRecords;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class TeacherImport implements ToCollection, WithHeadingRow
{
    public int $count = 0;

    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) {
            if ($row->filter(fn ($v) => $v !== null && $v !== '')->isEmpty()) {
                continue;
            }
            $data = $row->only(['name', 'cnic', 'gender', 'phone', 'email', 'joining_date', 'status', 'qualification', 'experience'])->toArray();
            $data['department_id'] = Department::where('name', $row['department'] ?? '')->value('id');
            $data['designation_id'] = Designation::where('name', $row['designation'] ?? '')->value('id');
            $data += ['status' => 'Active', 'basic_salary' => $row['basic_salary'] ?? null, 'house_allowance' => $row['house_allowance'] ?? 0, 'medical_allowance' => $row['medical_allowance'] ?? 0, 'transport_allowance' => $row['transport_allowance'] ?? 0, 'other_allowance' => $row['other_allowance'] ?? $row['allowances'] ?? 0, 'fixed_deductions' => $row['fixed_deductions'] ?? $row['deductions'] ?? 0, 'bank_name' => $row['bank_name'] ?? null, 'account_no' => isset($row['account_no']) ? (string) $row['account_no'] : null];
            $form = new TeacherRequest;
            $form->merge($data);
            $validator = Validator::make($data, $form->rules());
            if ($validator->fails()) {
                throw ValidationException::withMessages(['file' => 'Row '.($index + 2).': '.implode(' ', $validator->errors()->all())]);
            }
            app(TeacherRecords::class)->save($validator->validated());
            $this->count++;
        }
    }
}
