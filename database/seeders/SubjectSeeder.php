<?php

namespace Database\Seeders;

use App\Models\SchoolClass;
use App\Models\Subject;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SubjectSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $subjects = [];
            foreach (['English' => 'ENG', 'Urdu' => 'URD', 'Mathematics' => 'MAT', 'Science' => 'SCI', 'Physics' => 'PHY', 'Chemistry' => 'CHM', 'Biology' => 'BIO', 'Computer' => 'CMP', 'Islamiat' => 'ISL', 'Pak Studies' => 'PAK', 'Social Studies' => 'SOC', 'Drawing' => 'DRW'] as $name => $code) {
                $subjects[$name] = Subject::withTrashed()->firstOrCreate(['name' => $name], ['code' => $code, 'type' => in_array($name, ['Physics', 'Chemistry', 'Biology', 'Computer']) ? 'both' : ($name === 'Drawing' ? 'practical' : 'theory'), 'category' => in_array($name, ['Computer', 'Drawing']) ? 'optional' : 'compulsory', 'status' => 'Active']);
            }
            for ($grade = 1; $grade <= 10; $grade++) {
                $class = SchoolClass::firstOrCreate(['name' => 'Class '.$grade]);
                $names = $grade <= 5 ? ['English', 'Urdu', 'Mathematics', 'Science', 'Islamiat', 'Social Studies', 'Drawing'] : ($grade <= 8 ? ['English', 'Urdu', 'Mathematics', 'Science', 'Computer', 'Islamiat', 'Pak Studies', 'Social Studies'] : ['English', 'Urdu', 'Mathematics', 'Physics', 'Chemistry', 'Biology', 'Computer', 'Islamiat', 'Pak Studies']);
                foreach ($names as $order => $name) {
                    $subject = $subjects[$name];
                    if ($subject->trashed() || $subject->status !== 'Active') {
                        continue;
                    }
                    DB::table('class_subject')->insertOrIgnore(['school_class_id' => $class->id, 'subject_id' => $subject->id, 'total_marks' => 100, 'pass_marks' => 33, 'practical_marks' => $subject->type === 'both' ? 25 : ($subject->type === 'practical' ? 100 : 0), 'is_compulsory' => $subject->category === 'compulsory', 'display_order' => $order + 1, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        });
    }
}
