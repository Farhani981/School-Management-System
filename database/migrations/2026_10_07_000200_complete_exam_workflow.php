<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', fn (Blueprint $t) => $t->json('result_snapshot')->nullable());
        Schema::table('quiz_results', fn (Blueprint $t) => $t->string('public_remark', 255)->nullable());
        Schema::table('exam_settings', function (Blueprint $t) {
            $t->string('school_name')->default('School Management System');
            $t->string('school_address')->nullable();
            $t->string('school_contact')->nullable();
            $t->string('school_logo_path')->nullable();
        });
        Schema::create('parent_student', function (Blueprint $t) {
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $t->foreignId('verified_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->primary(['user_id', 'student_id']);
        });
        Schema::create('exam_student_comments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $t->foreignId('student_id')->constrained()->restrictOnDelete();
            $t->text('teacher_remark')->nullable();
            $t->text('principal_remark')->nullable();
            $t->foreignId('entered_by')->constrained('users');
            $t->timestamps();
            $t->unique(['exam_id', 'student_id']);
        });
        Schema::create('exam_reminders', function (Blueprint $t) {
            $t->id();
            $t->foreignId('exam_schedule_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('kind');
            $t->date('reminder_date');
            $t->timestamp('created_at');
            $t->unique(['exam_schedule_id', 'user_id', 'kind', 'reminder_date'], 'exam_reminder_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_reminders');
        Schema::dropIfExists('exam_student_comments');
        Schema::dropIfExists('parent_student');
        Schema::table('exam_settings', fn (Blueprint $t) => $t->dropColumn(['school_name','school_address','school_contact','school_logo_path']));
        Schema::table('quiz_results', fn (Blueprint $t) => $t->dropColumn('public_remark'));
        Schema::table('exams', fn (Blueprint $t) => $t->dropColumn('result_snapshot'));
    }
};
