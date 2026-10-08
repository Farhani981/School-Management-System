<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->text('description')->nullable();
            $table->timestamp('result_published_at')->nullable();
            $table->foreignId('result_published_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->json('result_settings_snapshot')->nullable();
            $table->unsignedInteger('result_publication_version')->default(0);
        });
        Schema::table('exam_schedules', function (Blueprint $table) {
            $table->dateTime('marks_deadline')->nullable();
            $table->boolean('allow_late_submission')->default(false);
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('roster_frozen_at')->nullable();
            $table->index(['marks_status', 'marks_deadline']);
        });
        Schema::table('quizzes', function (Blueprint $table) {
            $table->foreignId('exam_schedule_id')->nullable()->unique()->constrained('exam_schedules')->restrictOnDelete();
        });

        Schema::create('exam_roster_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained('quizzes')->restrictOnDelete();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->string('admission_no');
            $table->string('student_name');
            $table->string('roll_number')->nullable();
            $table->timestamps();
            $table->unique(['quiz_id', 'student_id']);
        });

        Schema::table('exam_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('exam_schedule_id')->nullable()->change();
            $table->foreignId('exam_id')->nullable()->constrained('exams')->restrictOnDelete();
            $table->index(['exam_id', 'created_at']);
        });
        $this->backfillLinks();
    }

    public function backfillLinks(): void
    {
        // Link only exact subject/section matches; preserve marks and statuses.
        DB::table('quizzes')->whereNotNull('exam_subject_id')->whereNull('exam_schedule_id')->orderBy('id')->chunkById(200, function ($quizzes) {
            foreach ($quizzes as $quiz) {
                $schedule = DB::table('exam_schedules')->where('exam_subject_id', $quiz->exam_subject_id)
                    ->where('section_id', $quiz->section_id)->first(['id']);
                if ($schedule) {
                    DB::table('quizzes')->where('id', $quiz->id)->update(['exam_schedule_id' => $schedule->id]);
                }
            }
        });
        DB::table('exam_logs')->whereNotNull('exam_schedule_id')->whereNull('exam_id')->orderBy('id')->chunkById(200, function ($logs) {
            foreach ($logs as $log) {
                $examId = DB::table('exam_schedules')->join('exam_subjects', 'exam_subjects.id', '=', 'exam_schedules.exam_subject_id')
                    ->where('exam_schedules.id', $log->exam_schedule_id)->value('exam_subjects.exam_id');
                if ($examId) DB::table('exam_logs')->where('id', $log->id)->update(['exam_id' => $examId]);
            }
        });
    }

    public function down(): void
    {
        // Do not silently delete exam-level audit history to make rollback possible.
        if (DB::table('exam_logs')->whereNull('exam_schedule_id')->exists()) {
            throw new RuntimeException('Cannot remove workflow foundation while exam-level audit records exist.');
        }
        Schema::table('exam_logs', function (Blueprint $table) {
            $table->dropIndex(['exam_id', 'created_at']);
            $table->dropConstrainedForeignId('exam_id');
            $table->unsignedBigInteger('exam_schedule_id')->nullable(false)->change();
        });
        Schema::dropIfExists('exam_roster_entries');
        Schema::table('quizzes', function (Blueprint $table) {
            $table->dropUnique(['exam_schedule_id']);
            $table->dropConstrainedForeignId('exam_schedule_id');
        });
        Schema::table('exam_schedules', function (Blueprint $table) {
            $table->dropIndex(['marks_status', 'marks_deadline']);
            $table->dropConstrainedForeignId('submitted_by');
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['marks_deadline', 'allow_late_submission', 'submitted_at', 'reviewed_at', 'roster_frozen_at']);
        });
        Schema::table('exams', function (Blueprint $table) {
            $table->dropConstrainedForeignId('result_published_by');
            $table->dropColumn(['description', 'result_published_at', 'result_settings_snapshot', 'result_publication_version']);
        });
    }
};
