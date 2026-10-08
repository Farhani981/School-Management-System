<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubjectRequest;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SubjectController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['search' => 'nullable|string|max:255', 'type' => ['nullable', Rule::in(['theory', 'practical', 'both'])], 'status' => ['nullable', Rule::in(['Active', 'Inactive'])], 'sort' => ['nullable', Rule::in(['name', 'code', 'type', 'status', 'classes_count'])], 'direction' => ['nullable', Rule::in(['asc', 'desc'])]]);
        $query = Subject::withCount('classes');
        if ($request->filled('search')) {
            $query->where(fn ($q) => $q->where('name', 'like', '%'.$request->search.'%')->orWhere('code', 'like', '%'.$request->search.'%'));
        }
        foreach (['type', 'status'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->input($field));
            }
        }

        return view('subjects.index', ['subjects' => $query->orderBy($request->input('sort', 'name'), $request->input('direction', 'asc'))->orderBy('id')->paginate(15)->withQueryString()]);
    }

    public function create()
    {
        return view('subjects.form', ['subject' => new Subject(['type' => 'theory', 'category' => 'compulsory', 'status' => 'Active'])]);
    }

    public function store(SubjectRequest $request)
    {
        Subject::create($request->validated());

        return redirect()->route('subjects.index')->with('success', 'Subject added.');
    }

    public function edit(Subject $subject)
    {
        return view('subjects.form', compact('subject'));
    }

    public function update(SubjectRequest $request, Subject $subject)
    {
        DB::transaction(fn () => Subject::whereKey($subject->id)->lockForUpdate()->firstOrFail()->update($request->validated()), 3);

        return redirect()->route('subjects.index')->with('success', 'Subject updated.');
    }

    public function destroy(Subject $subject)
    {
        $blocked = DB::transaction(function () use ($subject) {
            $subject = Subject::whereKey($subject->id)->lockForUpdate()->firstOrFail();
            if ($subject->classes()->exists() || $subject->teacherAssignments()->exists() || $subject->marks()->exists()) {
                return true;
            }
            $subject->delete();

            return false;
        }, 3);

        return redirect()->route('subjects.index')->with($blocked ? 'error' : 'success', $blocked ? 'Cannot delete this subject: it is assigned to a class/teacher or has recorded marks. Remove assignments first; marks must be preserved.' : 'Subject archived.');
    }
}
