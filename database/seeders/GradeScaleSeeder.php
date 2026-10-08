<?php

namespace Database\Seeders;

use App\Models\GradeScale;
use Illuminate\Database\Seeder;

class GradeScaleSeeder extends Seeder
{
    public function run(): void
    {
        $scales = [
            [
                'min_percent' => 90.00,
                'max_percent' => 100.00,
                'grade' => 'A+',
                'remark' => 'Outstanding',
            ],
            [
                'min_percent' => 80.00,
                'max_percent' => 89.99,
                'grade' => 'A',
                'remark' => 'Excellent',
            ],
            [
                'min_percent' => 70.00,
                'max_percent' => 79.99,
                'grade' => 'B',
                'remark' => 'Very Good',
            ],
            [
                'min_percent' => 60.00,
                'max_percent' => 69.99,
                'grade' => 'C',
                'remark' => 'Good',
            ],
            [
                'min_percent' => 50.00,
                'max_percent' => 59.99,
                'grade' => 'D',
                'remark' => 'Satisfactory',
            ],
            [
                'min_percent' => 0.00,
                'max_percent' => 49.99,
                'grade' => 'F',
                'remark' => 'Fail',
            ],
        ];

        foreach ($scales as $scale) {
            GradeScale::firstOrCreate(
                ['grade' => $scale['grade']],
                $scale
            );
        }
    }
}
