<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AcademicSetupSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'school_classes' => array_merge(['Nursery', 'KG'], array_map(fn ($grade) => 'Class '.$grade, range(1, 10))),
            'school_sections' => ['A', 'B'],
            'academic_sessions' => ['2026-2027'],
        ] as $table => $names) {
            foreach ($names as $name) {
                DB::table($table)->insertOrIgnore(['name' => $name, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }
}
