<?php

use App\Http\Controllers\Teacher\AttendanceController;
use App\Http\Controllers\Teacher\ClassController;
use App\Http\Controllers\Teacher\DashboardController;
use App\Http\Controllers\Teacher\PanelController;
use App\Http\Controllers\Teacher\QuizController;
use App\Http\Controllers\Teacher\QuizMarksController;
use App\Models\TeacherAssignment;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

Route::get('dashboard', DashboardController::class)->name('dashboard');
Route::get('exams', [\App\Http\Controllers\Teacher\ExamController::class, 'index'])->name('exams.index');
Route::get('exams/schedule', [\App\Http\Controllers\Teacher\ExamController::class, 'schedule'])->name('exams.schedule');
Route::get('exams/papers/{paper}/marks', [\App\Http\Controllers\Teacher\ExamMarksController::class, 'edit'])->name('exams.marks.edit');
Route::post('exams/papers/{paper}/marks', [\App\Http\Controllers\Teacher\ExamMarksController::class, 'save'])->name('exams.marks.save');
Route::post('exams/papers/{paper}/marks/import', [\App\Http\Controllers\Teacher\ExamMarksController::class, 'import'])->name('exams.marks.import');
Route::get('classes', [ClassController::class, 'index'])->name('classes.index');
Route::get('classes/{assignment}/students', [ClassController::class, 'students'])->name('classes.students');
Route::get('attendance', [AttendanceController::class, 'index'])->name('attendance.index');
Route::get('attendance/{assignment}/register', [AttendanceController::class, 'register'])->name('attendance.register');
Route::get('attendance/{assignment}', [AttendanceController::class, 'show'])->name('attendance.show');
Route::post('attendance/{assignment}', [AttendanceController::class, 'store'])->name('attendance.store');

// Quizzes & Results
Route::get('quizzes', [QuizController::class, 'index'])->name('quizzes.index');
Route::get('quizzes/create', [QuizController::class, 'create'])->name('quizzes.create');
Route::post('quizzes', [QuizController::class, 'store'])->name('quizzes.store');
Route::get('quizzes/{quiz}', [QuizController::class, 'show'])->name('quizzes.show');
Route::get('quizzes/{quiz}/edit', [QuizController::class, 'edit'])->name('quizzes.edit');
Route::put('quizzes/{quiz}', [QuizController::class, 'update'])->name('quizzes.update');
Route::post('quizzes/{quiz}/duplicate', [QuizController::class, 'duplicate'])->name('quizzes.duplicate');
Route::delete('quizzes/{quiz}', [QuizController::class, 'destroy'])->name('quizzes.destroy');
Route::get('quizzes/{quiz}/marks', [QuizMarksController::class, 'edit'])->name('quizzes.marks');
Route::post('quizzes/{quiz}/marks', [QuizMarksController::class, 'update'])->name('quizzes.marks.update');
Route::post('quizzes/{quiz}/marks/import', [QuizMarksController::class, 'import'])->name('quizzes.marks.import');
Route::get('quizzes/{quiz}/export/{format}', [QuizController::class, 'export'])->name('quizzes.export');

// Class shortcuts
Route::get('classes/{assignment}/quiz', function (TeacherAssignment $assignment) {
    Gate::authorize('view', $assignment);
    return redirect()->route('teacher.quizzes.index', [
        'class_id' => $assignment->class_id,
        'section_id' => $assignment->section_id,
        'subject_id' => $assignment->subject_id,
    ]);
})->name('classes.quiz');

Route::get('classes/{assignment}/progress', function (TeacherAssignment $assignment) {
    Gate::authorize('view', $assignment);
    return redirect()->route('teacher.progress.index', [
        'class_id' => $assignment->class_id,
        'section_id' => $assignment->section_id,
        'tab' => 'class',
    ]);
})->name('classes.progress');

// Student Progress Module
Route::get('progress', [\App\Http\Controllers\Teacher\StudentProgressController::class, 'index'])->name('progress.index');
Route::get('progress/export/class', [\App\Http\Controllers\Teacher\StudentProgressController::class, 'exportClassCsv'])->name('progress.export.class');
Route::get('progress/{student}/export/pdf', [\App\Http\Controllers\Teacher\StudentProgressController::class, 'exportPdf'])->name('progress.export.pdf');
Route::post('progress/{student}/notes', [\App\Http\Controllers\Teacher\StudentProgressController::class, 'storeNote'])->name('progress.notes.store');
Route::put('progress/notes/{note}', [\App\Http\Controllers\Teacher\StudentProgressController::class, 'updateNote'])->name('progress.notes.update');
Route::delete('progress/notes/{note}', [\App\Http\Controllers\Teacher\StudentProgressController::class, 'destroyNote'])->name('progress.notes.destroy');

// Secondary pages
foreach (['timetable' => 'Timetable', 'leaves' => 'Leaves', 'notices' => 'Notices', 'profile' => 'My Profile'] as $path => $title) {
    Route::get($path, [PanelController::class, 'show'])->defaults('page', $path)->defaults('title', $title)->name($path.'.index');
}
