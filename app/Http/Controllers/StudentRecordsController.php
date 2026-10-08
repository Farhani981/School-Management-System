<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Services\StudentRecords;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class StudentRecordsController extends Controller
{
    public static function options(): array
    {
        return [
            'class_id' => DB::table('school_classes')->pluck('name', 'id')->sort(function ($first, $second) {
                $rank = fn ($name) => match (mb_strtolower(trim($name))) {
                    'nursery' => 0,
                    'kg' => 1,
                    default => 2,
                };
                return ($rank($first) <=> $rank($second)) ?: strnatcasecmp($first, $second);
            }),
            'section_id' => DB::table('school_sections')->orderBy('name')->pluck('name', 'id'),
            'academic_session_id' => DB::table('academic_sessions')->orderByDesc('name')->pluck('name', 'id'),
        ];
    }

    public function documents(Request $request, Student $student, StudentRecords $records)
    {
        $request->validate(['documents' => ['required', 'array', 'min:1', 'max:10'], 'documents.*' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120']]);
        $records->save($student, [], $request->file('documents'));
        return back()->withFragment('documents')->with('success', 'Documents uploaded.');
    }

    public function download(Student $student, int $document)
    {
        $document = $student->documents()->findOrFail($document);
        abort_unless(Storage::disk('local')->exists($document->path), 404);
        return Storage::disk('local')->download($document->path, $document->name);
    }

    public function deleteDocument(Student $student, int $document)
    {
        $document = $student->documents()->findOrFail($document);
        DB::transaction(function () use ($student, $document) {
            $student->histories()->create(['event' => 'Document deleted', 'notes' => $document->name]);
            $document->delete();
        });
        Storage::disk('local')->delete($document->path);
        return back()->withFragment('documents')->with('success', 'Document deleted.');
    }

    public function promote(Request $request, Student $student)
    {
        $data = $request->validate([
            'class_id' => ['required', 'integer', 'exists:school_classes,id'],
            'section_id' => ['required', 'integer', 'exists:school_sections,id'],
            'academic_session_id' => ['required', 'integer', 'exists:academic_sessions,id'],
            'roll_number' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        DB::transaction(function () use ($student, $data) {
            DB::table('school_classes')->where('id', $data['class_id'])->lockForUpdate()->first();
            $student = Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            app(\App\Services\StudentPromotion::class)->promote($student, $data);
        });
        return back()->with('success', 'Student promoted and history saved.');
    }
}
