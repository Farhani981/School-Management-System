<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('students')->orderBy('id')->each(function ($student) {
            if (!ctype_digit((string) $student->admission_no)) return;
            $year = substr($student->admission_date, 0, 4);
            $number = sprintf('ADM-%s-%04d', $year, (int) $student->admission_no);
            if (DB::table('students')->where('admission_no', $number)->exists()) {
                $number = sprintf('ADM-%s-%04d', $year, $student->id);
            }
            if (!DB::table('students')->where('admission_no', $number)->exists()) {
                DB::table('students')->where('id', $student->id)->update(['admission_no' => $number]);
            }
        });
    }

    public function down(): void
    {
        // Admission identifiers must remain stable once issued.
    }
};
