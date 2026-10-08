<?php

namespace App\Http\Controllers;

use App\Http\Requests\LibraryMemberRequest;
use App\Models\Activity;
use App\Models\LibraryMember;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LibraryMemberController extends Controller
{
    private const RELATIONS = ['student.schoolClass', 'student.section', 'teacher.department'];

    public function index(Request $request)
    {
        Gate::authorize('viewAny', LibraryMember::class);
        $filters = $request->validate(['search' => 'nullable|string|max:150', 'member_type' => ['nullable', Rule::in(LibraryMember::TYPES)],
            'status' => ['nullable', Rule::in(LibraryMember::STATUSES)], 'per_page' => ['nullable', Rule::in([10, 25, 50])]]);
        $query = LibraryMember::with(self::RELATIONS);
        if ($request->filled('search')) {
            $term = '%'.$filters['search'].'%';
            $query->where(fn ($q) => $q->where('member_id', 'like', $term)
                ->orWhereHas('student', fn ($s) => $s->where(function ($name) use ($filters) {
                    foreach (preg_split('/\s+/u', trim($filters['search'])) as $word) {
                        $name->where(fn ($part) => $part->where('first_name', 'like', '%'.$word.'%')->orWhere('last_name', 'like', '%'.$word.'%'));
                    }
                })->orWhere('admission_no', 'like', $term)->orWhere('phone', 'like', $term))
                ->orWhereHas('teacher', fn ($t) => $t->where('name', 'like', $term)->orWhere('employee_id', 'like', $term)->orWhere('phone', 'like', $term)));
        }
        foreach (['member_type', 'status'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $filters[$field]);
            }
        }

        return view('library.members.index', ['members' => $query->orderByDesc('id')->paginate((int) ($filters['per_page'] ?? 10))->withQueryString(), 'filters' => $filters]);
    }

    public function create()
    {
        Gate::authorize('create', LibraryMember::class);
        $students = Student::with(['schoolClass', 'section'])->where('status', 'Active')->whereNotIn('id', LibraryMember::whereNotNull('student_id')->select('student_id'))->orderBy('first_name')->orderBy('last_name')->get();
        $teachers = Teacher::where('status', 'Active')->whereNotIn('id', LibraryMember::whereNotNull('teacher_id')->select('teacher_id'))->orderBy('name')->get();

        return view('library.members.form', ['member' => new LibraryMember(['member_type' => 'Student', 'registration_date' => today(), 'status' => 'Active',
            'borrowing_limit' => config('library.borrowing_limits.Student')]), 'students' => $students, 'teachers' => $teachers]);
    }

    public function store(LibraryMemberRequest $request)
    {
        $data = $request->validated();
        $member = DB::transaction(function () use ($request, $data) {
            $student = $data['member_type'] === 'Student';
            $field = $student ? 'student_id' : 'teacher_id';
            $person = ($student ? Student::query() : Teacher::query())->whereKey($data[$field])->where('status', 'Active')->lockForUpdate()->first();
            if (!$person || LibraryMember::where($field, $person->id)->exists()) {
                throw ValidationException::withMessages([$field => 'Choose an active person who is not already a library member.']);
            }
            $member = LibraryMember::create(array_merge($data, [$student ? 'teacher_id' : 'student_id' => null]));
            $member->member_id = ($student ? 'STU' : 'TCH').'-LIB-'.str_pad((string) $member->id, 5, '0', STR_PAD_LEFT);
            $member->save();
            $this->record($request, $member, 'created');

            return $member;
        });

        return redirect()->route('library.members.show', $member)->with('success', 'Library member added.');
    }

    public function show(Request $request, LibraryMember $member)
    {
        Gate::authorize('view', $member);
        $layout = $request->user()->hasAnyRole(['Admin', 'Librarian']) ? 'layouts.admin' : ($request->user()->hasRole('Teacher') ? 'layouts.teacher' : 'layouts.portal');

        return view('library.members.show', ['member' => $member->load(self::RELATIONS), 'layout' => $layout,
            'loans' => $member->transactions()->with(['book', 'copy', 'member.student', 'member.teacher'])->orderByDesc('id')->paginate(10, ['*'], 'loan_page')]);
    }

    public function edit(LibraryMember $member)
    {
        Gate::authorize('update', $member);

        return view('library.members.form', ['member' => $member->load(self::RELATIONS)]);
    }

    public function update(LibraryMemberRequest $request, LibraryMember $member)
    {
        DB::transaction(function () use ($request, $member) {
            $member = LibraryMember::whereKey($member->id)->lockForUpdate()->firstOrFail();
            $member->update($request->safe()->only(['registration_date', 'borrowing_limit', 'status']));
            $this->record($request, $member, 'updated');
        });

        return redirect()->route('library.members.show', $member)->with('success', 'Library member updated.');
    }

    private function record(Request $request, LibraryMember $member, string $action): void
    {
        Activity::create(['description' => 'Library member '.$action.': '.$member->member_id, 'type' => 'library',
            'subject' => 'LibraryMember:'.$member->id, 'user_id' => $request->user()->id, 'created_at' => now()]);
    }
}
