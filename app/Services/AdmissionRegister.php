<?php

namespace App\Services;

use App\Models\Student;

class AdmissionRegister
{
    public function query(array $filters)
    {
        $query = Student::withTrashed();
        if (!empty($filters['from'])) $query->whereDate('admission_date', '>=', $filters['from']);
        if (!empty($filters['to'])) $query->whereDate('admission_date', '<=', $filters['to']);
        if (!empty($filters['class_id'])) $query->where('class_id', $filters['class_id']);
        return $query->orderByDesc('admission_date')->orderByDesc('id');
    }
}
