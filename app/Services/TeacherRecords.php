<?php

namespace App\Services;

use App\Models\SalaryStructure;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TeacherRecords
{
    public function save(array $data, ?Teacher $teacher = null): Teacher
    {
        $stored = [];
        $oldPhoto = $teacher?->photo;
        try {
            $result = DB::transaction(function () use ($data, $teacher, &$stored) {
                $teacher ??= new Teacher;
                $teacher->fill(Arr::only($data, ['name', 'cnic', 'gender', 'date_of_birth', 'phone', 'email', 'address', 'emergency_contact', 'department_id', 'designation_id', 'joining_date', 'status', 'qualification', 'experience']));
                if (! $teacher->exists) {
                    $year = now()->year;
                    DB::table('teacher_sequences')->insertOrIgnore(['year' => $year, 'number' => 0]);
                    DB::table('teacher_sequences')->where('year', $year)->increment('number');
                    $number = DB::table('teacher_sequences')->where('year', $year)->value('number');
                    $teacher->employee_id = sprintf('EMP-%d-%03d', $year, $number);
                }
                if (! empty($data['photo'])) {
                    $teacher->photo = $data['photo']->store('teachers/photos', 'public');
                    $stored[] = ['public', $teacher->photo];
                }
                if (! empty($data['create_login']) || $teacher->user_id) {
                    $user = $teacher->user ?? new User;
                    $user->fill(['name' => $teacher->name, 'email' => $data['login_email'], 'role' => 'Teacher']);
                    if (! empty($data['password'])) {
                        $user->password = $data['password'];
                    }
                    $user->save();
                    $teacher->user_id = $user->id;
                }
                $teacher->save();
                $salary = ['basic_salary' => $data['basic_salary']];
                foreach (SalaryStructure::ALLOWANCE_FIELDS as $field) {
                    $salary[$field] = $data[$field] ?? ($field === 'other_allowance' ? ($data['allowances'] ?? 0) : 0);
                }
                $salary['fixed_deductions'] = $data['fixed_deductions'] ?? $data['deductions'] ?? 0;
                $salary['bank_name'] = $data['bank_name'] ?? null;
                $salary['account_no'] = $data['account_no'] ?? null;
                // Keep legacy totals compatible with older reports and migration rollback.
                $salary['allowances'] = array_sum(array_map(fn ($field) => (int) round((float) $salary[$field] * 100), SalaryStructure::ALLOWANCE_FIELDS)) / 100;
                $salary['deductions'] = $salary['fixed_deductions'];
                $teacher->salaryStructure()->updateOrCreate([], $salary);
                foreach ($data['documents'] ?? [] as $file) {
                    $path = $file->store('teachers/documents', 'local');
                    $stored[] = ['local', $path];
                    $teacher->documents()->create(['name' => $file->getClientOriginalName(), 'path' => $path]);
                }

                return $teacher;
            }, 3);
        } catch (\Throwable $exception) {
            foreach ($stored as [$disk,$path]) {
                Storage::disk($disk)->delete($path);
            }
            throw $exception;
        }
        if ($oldPhoto && $oldPhoto !== $result->photo) {
            Storage::disk('public')->delete($oldPhoto);
        }

        return $result;
    }
}
