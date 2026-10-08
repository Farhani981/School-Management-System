<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grade_scales', function (Blueprint $table) {
            $table->id();
            $table->decimal('min_percent', 5, 2);
            $table->decimal('max_percent', 5, 2);
            $table->string('grade', 10);
            $table->string('remark', 100)->nullable();
            $table->timestamps();
        });

        Schema::create('quizzes', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('type'); // quiz, test, monthly, midterm, final, assignment
            $table->foreignId('class_id')->constrained('school_classes')->cascadeOnDelete();
            $table->foreignId('section_id')->constrained('school_sections')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->foreignId('session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->date('date');
            $table->decimal('total_marks', 6, 2);
            $table->decimal('passing_marks', 6, 2);
            $table->string('status')->default('draft'); // draft, published, closed
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['teacher_id', 'status']);
            $table->index(['class_id', 'section_id', 'subject_id', 'session_id']);
        });

        Schema::create('quiz_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained('quizzes')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->decimal('marks_obtained', 6, 2)->nullable();
            $table->string('attendance_status')->default('present'); // present, absent, exempt
            $table->string('remarks')->nullable();
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('entered_at')->nullable();
            $table->timestamps();

            $table->unique(['quiz_id', 'student_id']);
            $table->index(['quiz_id', 'attendance_status']);
        });

        Schema::create('quiz_result_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_result_id')->nullable()->constrained('quiz_results')->nullOnDelete();
            $table->foreignId('quiz_id')->constrained('quizzes')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->decimal('old_marks', 6, 2)->nullable();
            $table->decimal('new_marks', 6, 2)->nullable();
            $table->string('old_attendance')->nullable();
            $table->string('new_attendance')->nullable();
            $table->text('reason');
            $table->foreignId('changed_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_result_audits');
        Schema::dropIfExists('quiz_results');
        Schema::dropIfExists('quizzes');
        Schema::dropIfExists('grade_scales');
    }
};
