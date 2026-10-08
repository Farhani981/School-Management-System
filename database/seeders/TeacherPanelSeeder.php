<?php

namespace Database\Seeders;

use App\Models\AcademicSession;
use App\Models\Department;
use App\Models\Designation;
use App\Models\SchoolClass;
use App\Models\SchoolSection;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class TeacherPanelSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            foreach (['Admin', 'Principal', 'Teacher'] as $name) {
                Role::findOrCreate($name, 'web');
            }
            // First creation sets demo credentials. Re-running does not reset passwords.
            $user = User::firstOrCreate(['email' => 'teacher@test.com'], ['name' => 'Demo Teacher', 'password' => 'password', 'role' => 'Teacher']);
            if ($user->role !== 'Teacher' || $user->hasAnyRole(['Admin', 'Principal'])) {
                throw new \RuntimeException('teacher@test.com belongs to a non-teacher account; refusing to change its access.');
            }
            $user->assignRole('Teacher');
            $department = Department::firstOrCreate(['name' => 'Teaching']);
            $designation = Designation::firstOrCreate(['name' => 'Teacher']);
            $teacher = Teacher::firstOrCreate(['user_id' => $user->id], ['employee_id' => 'DEMO-TEACHER-001', 'name' => $user->name,
                'cnic' => '99999-0000001-1', 'gender' => 'Male', 'phone' => '03001234567', 'email' => $user->email,
                'department_id' => $department->id, 'designation_id' => $designation->id, 'joining_date' => today(), 'status' => 'Active']);
            $session = AcademicSession::where('is_current', true)->first() ?? AcademicSession::firstOrCreate(['name' => today()->year.'-'.(today()->year + 1)], ['is_current' => true]);
            $section = SchoolSection::firstOrCreate(['name' => 'Teacher Demo']);
            $subject = Subject::firstOrCreate(['code' => 'TDEMO-ENG'], ['name' => 'Teacher Demo English', 'type' => 'theory', 'category' => 'compulsory', 'status' => 'Active']);
            foreach ([1, 2] as $number) {
                $class = SchoolClass::firstOrCreate(['name' => 'Class '.$number]);
                // Dedicated demo section prevents conflicts with real teaching assignments.
                $assignment = TeacherAssignment::firstOrCreate(['class_id' => $class->id, 'section_id' => $section->id, 'subject_id' => $subject->id, 'session_id' => $session->id], ['teacher_id' => $teacher->id, 'is_class_teacher' => $number === 1]);
                if ($assignment->teacher_id !== $teacher->id) {
                    throw new \RuntimeException('Demo assignment is already owned by another teacher.');
                }
                foreach ([1, 2, 3] as $roll) {
                    Student::firstOrCreate(['admission_no' => 'TDEMO-'.$number.'-'.$roll], ['first_name' => 'Demo Student '.$number, 'last_name' => (string) $roll,
                        'father_name' => 'Demo Parent '.$number.'-'.$roll, 'gender' => $roll === 2 ? 'Female' : 'Male', 'phone' => '03001234567', 'address' => 'Demo school',
                        'date_of_birth' => '2018-01-01', 'admission_date' => today(), 'class_id' => $class->id, 'section_id' => $section->id,
                        'academic_session_id' => $session->id, 'roll_number' => (string) $roll, 'status' => 'Active']);
                }
            }
        });
        $this->command?->info('Teacher demo: teacher@test.com / password (first creation only). Two assignments, six demo students.');
    }
}
