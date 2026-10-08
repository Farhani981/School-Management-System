<?php

namespace App\Http\Controllers;

use App\Models\Student;

class StudentIdCardController extends Controller
{
    public function __invoke(Student $student)
    {
        return app(\App\Services\StudentIdCards::class)->download(collect([$student]), $student->admission_no.'-id-card.pdf');
    }
}
