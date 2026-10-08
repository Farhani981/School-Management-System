<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class RollNumbers
{
    public function next(int $session, int $class, int $section): string
    {
        $key = ['session_id' => $session, 'class_id' => $class, 'section_id' => $section];
        DB::table('roll_sequences')->insertOrIgnore($key + ['last_number' => 0]);
        $counter = DB::table('roll_sequences')->where($key)->lockForUpdate()->first();
        $existing = DB::table('students')->where('academic_session_id', $session)->where('class_id', $class)->where('section_id', $section)->pluck('roll_number');
        $largest = $existing->filter(fn ($number) => ctype_digit((string) $number))->map(fn ($number) => (int) $number)->max() ?? 0;
        $number = max($counter->last_number, $largest) + 1;
        DB::table('roll_sequences')->where('id', $counter->id)->update(['last_number' => $number]);
        return (string) $number;
    }
}
