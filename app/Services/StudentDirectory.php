<?php

namespace App\Services;

use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Cache;
use App\Models\SchoolClass;

class StudentDirectory
{
    public function filters(Request $request): array
    {
        $filters = $request->validate([
            'missing_assignment' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(Student::STATUSES)],
            'gender' => ['nullable', Rule::in(['Male', 'Female', 'Other'])],
            'class_id' => ['nullable', 'integer', 'exists:school_classes,id'],
            'section_id' => ['nullable', 'integer', 'exists:school_sections,id'],
            'academic_session_id' => ['nullable', 'integer', 'exists:academic_sessions,id'],
            'sort' => ['nullable', Rule::in(['name', 'admission_no', 'roll_number', 'class', 'status'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', Rule::in([10, 25, 50, 100])],
        ]);
        if (!$request->has('status')) $filters['status'] = 'Active';
        return $filters;
    }

    public function query(array $filters)
    {
        $query = Student::with(['schoolClass', 'section']);
        if (!empty($filters['missing_assignment'])) $query->where(fn ($q) => $q->whereNull('class_id')->orWhereNull('section_id')->orWhereNull('academic_session_id'));
        if (!empty($filters['search'])) {
            $query->where(function ($query) use ($filters) {
                foreach (['admission_no', 'first_name', 'last_name', 'father_name', 'phone', 'roll_number'] as $field) {
                    $query->orWhere($field, 'like', '%'.$filters['search'].'%');
                }
                $fullName = \Illuminate\Support\Facades\DB::getDriverName() === 'mysql' ? "CONCAT(first_name, ' ', COALESCE(last_name, ''))" : "first_name || ' ' || COALESCE(last_name, '')";
                $query->orWhereRaw($fullName.' LIKE ?', ['%'.$filters['search'].'%']);
            });
        }
        foreach (['status', 'gender', 'class_id', 'section_id', 'academic_session_id'] as $field) {
            if (!empty($filters[$field])) $query->where($field, $filters[$field]);
        }
        $direction = $filters['direction'] ?? 'asc';
        if (($filters['sort'] ?? '') === 'name') $query->orderBy('first_name', $direction)->orderBy('last_name', $direction);
        elseif (($filters['sort'] ?? '') === 'class') $query->orderBy(SchoolClass::select('name')->whereColumn('school_classes.id', 'students.class_id'), $direction);
        elseif (!empty($filters['sort'])) $query->orderBy($filters['sort'], $direction);
        return $query->orderByDesc('id');
    }

    public function counts(array $filters): array
    {
        // One grouped query for all class and section pills; class/section selection does not hide sibling pills.
        $base = array_intersect_key($filters, array_flip(['search', 'status', 'gender', 'academic_session_id', 'missing_assignment']));
        ksort($base);
        $key = 'student-directory:'.Cache::get('dashboard:revision', 'initial').':'.hash('sha256', json_encode($base));
        $groups = Cache::remember($key, now()->addMinutes(5), fn () => $this->query($base)->withoutEagerLoads()->reorder()
            ->select('class_id', 'section_id')->selectRaw('COUNT(*) as total')->groupBy('class_id', 'section_id')->get()->toArray());
        $classes = $sections = [];
        $total = 0;
        foreach ($groups as $group) {
            $count = (int) $group['total'];
            $total += $count;
            $classes[$group['class_id'] ?? 0] = ($classes[$group['class_id'] ?? 0] ?? 0) + $count;
            if (!empty($filters['class_id']) && (int) $group['class_id'] === (int) $filters['class_id']) {
                $sections[$group['section_id'] ?? 0] = ($sections[$group['section_id'] ?? 0] ?? 0) + $count;
            }
        }
        return ['classes' => $classes, 'sections' => $sections, 'total' => $total];
    }
}
