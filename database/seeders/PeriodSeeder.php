<?php

namespace Database\Seeders;

use App\Models\Period;
use Illuminate\Database\Seeder;

class PeriodSeeder extends Seeder
{
    public function run(): void
    {
        $defaultPeriods = [
            ['name' => 'Period 1', 'start_time' => '08:00:00', 'end_time' => '08:45:00', 'is_break' => false, 'sort_order' => 1, 'is_active' => true],
            ['name' => 'Period 2', 'start_time' => '08:45:00', 'end_time' => '09:30:00', 'is_break' => false, 'sort_order' => 2, 'is_active' => true],
            ['name' => 'Period 3', 'start_time' => '09:30:00', 'end_time' => '10:15:00', 'is_break' => false, 'sort_order' => 3, 'is_active' => true],
            ['name' => 'Morning Break', 'start_time' => '10:15:00', 'end_time' => '10:45:00', 'is_break' => true, 'sort_order' => 4, 'is_active' => true],
            ['name' => 'Period 4', 'start_time' => '10:45:00', 'end_time' => '11:30:00', 'is_break' => false, 'sort_order' => 5, 'is_active' => true],
            ['name' => 'Period 5', 'start_time' => '11:30:00', 'end_time' => '12:15:00', 'is_break' => false, 'sort_order' => 6, 'is_active' => true],
            ['name' => 'Period 6', 'start_time' => '12:15:00', 'end_time' => '13:00:00', 'is_break' => false, 'sort_order' => 7, 'is_active' => true],
            ['name' => 'Period 7', 'start_time' => '13:00:00', 'end_time' => '13:45:00', 'is_break' => false, 'sort_order' => 8, 'is_active' => true],
        ];

        foreach ($defaultPeriods as $data) {
            Period::firstOrCreate(
                ['sort_order' => $data['sort_order']],
                $data
            );
        }
    }
}
