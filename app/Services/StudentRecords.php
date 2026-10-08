<?php

namespace App\Services;

use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class StudentRecords
{
    public function save(Student $student, array $data, array $files = [], $photo = null): Student
    {
        $stored = [];
        $documents = [];
        $studentId = $student->exists ? $student->id : null;
        $oldPhoto = $student->photo;
        try {
            // Upload once so a database deadlock retry does not duplicate files.
            if ($photo) {
                $data['photo'] = $photo->store('students/photos', 'public');
                throw_unless($data['photo'], \RuntimeException::class, 'Unable to save photo.');
                $stored[] = ['public', $data['photo']];
            }
            foreach ($files as $file) {
                $path = $file->store('students/documents', 'local');
                throw_unless($path, \RuntimeException::class, 'Unable to save document.');
                $stored[] = ['local', $path];
                $documents[] = ['name' => mb_substr(basename($file->getClientOriginalName()), 0, 255), 'path' => $path];
            }
            $saved = DB::transaction(function () use ($studentId, $data, $documents, &$oldPhoto) {
                $new = $studentId === null;
                $student = $new ? new Student : Student::whereKey($studentId)->lockForUpdate()->firstOrFail();
                $oldPhoto = $student->photo;
                $before = $student->getAttributes();
                $student->fill($data);
                $generateAdmission = $new && empty($student->admission_no);
                if ($generateAdmission) {
                    $student->admission_no = app(AdmissionNumbers::class)->next((int) $student->admission_date->format('Y'));
                }
                if (ctype_digit((string) $student->admission_no)) {
                    $student->admission_no = sprintf('ADM-%s-%04d', $student->admission_date->format('Y'), (int) $student->admission_no);
                }
                $changes = [];
                foreach ($student->getDirty() as $field => $value) {
                    $changes[$field] = ['from' => $before[$field] ?? null, 'to' => $value];
                }
                $student->save();
                if ($new || $changes) {
                    $student->histories()->create(['event' => $new ? 'Admission' : 'Details updated', 'changes' => $changes]);
                }
                foreach ($documents as $document) {
                    $student->documents()->create($document);
                    $student->histories()->create(['event' => 'Document uploaded', 'notes' => $document['name']]);
                }
                return $student;
            }, 5);
        } catch (\Throwable $exception) {
            foreach ($stored as [$disk, $path]) {
                Storage::disk($disk)->delete($path);
            }
            if ($exception instanceof \Illuminate\Database\UniqueConstraintViolationException) {
                $field = str_contains($exception->getMessage(), 'admission_no') ? 'admission_no' : 'roll_number';
                throw \Illuminate\Validation\ValidationException::withMessages([$field => 'This '.str_replace('_', ' ', $field).' is already reserved.']);
            }
            throw $exception;
        }
        if ($photo && $oldPhoto) {
            Storage::disk('public')->delete($oldPhoto);
        }
        return $saved;
    }
}
