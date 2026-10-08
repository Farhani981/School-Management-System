<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class AdmissionNumbers
{
    public function next(int $year): string
    {
        return DB::transaction(function () use ($year) {
            DB::table('admission_sequences')->insertOrIgnore(['year' => $year, 'last_number' => 0]);
            do {
                // The write locks the yearly counter until the surrounding admission transaction commits.
                DB::table('admission_sequences')->where('year', $year)->increment('last_number');
                $sequence = DB::table('admission_sequences')->where('year', $year)->value('last_number');
                $number = sprintf('ADM-%d-%04d', $year, $sequence);
            } while (DB::table('students')->where('admission_no', $number)->exists());
            return $number;
        }, 5);
    }
}
