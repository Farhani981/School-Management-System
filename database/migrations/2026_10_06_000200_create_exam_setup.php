<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grade_scales', fn (Blueprint $t) => $t->decimal('gpa', 4, 2)->nullable());
        // Use school grade scales already shared with Teacher assessments.
        Schema::create('exam_settings', function (Blueprint $t) {
            $t->unsignedInteger('id')->primary();
            $t->string('pass_rule')->default('subject');
            $t->decimal('overall_pass_percent', 5, 2)->default(50);
            $t->boolean('absent_counts_as_fail')->default(false);
            $t->boolean('grace_enabled')->default(false);
            $t->decimal('grace_max_marks', 6, 2)->default(0);
            $t->unsignedInteger('default_marks_window_days')->default(14);
            $t->text('report_instructions')->nullable();
            $t->string('principal_signatory')->nullable();
            $t->string('class_teacher_label')->nullable();
            $t->timestamps();
        });
        DB::table('exam_settings')->insert(['id' => 1, 'created_at' => now(), 'updated_at' => now()]);
        Schema::create('exams', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('type');
            $t->foreignId('session_id')->constrained('academic_sessions');
            $t->string('term')->nullable();
            $t->date('start_date');
            $t->date('end_date');
            $t->decimal('weightage', 5, 2)->nullable();
            $t->string('status')->default('draft');
            $t->foreignId('created_by')->constrained('users');
            $t->timestamps();
            $t->index(['session_id', 'status']);
        });
        Schema::create('exam_classes', function (Blueprint $t) {
            $t->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $t->foreignId('class_id')->constrained('school_classes');
            $t->primary(['exam_id', 'class_id']);
        });
        Schema::create('exam_subjects', function (Blueprint $t) {
            $t->id();
            $t->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $t->foreignId('class_id')->constrained('school_classes');
            $t->foreignId('subject_id')->constrained('subjects');
            $t->decimal('total_marks', 6, 2);
            $t->decimal('passing_marks', 6, 2);
            $t->boolean('has_practical')->default(false);
            $t->decimal('theory_marks', 6, 2);
            $t->decimal('practical_marks', 6, 2)->default(0);
            $t->timestamps();
            $t->unique(['exam_id', 'class_id', 'subject_id']);
        });
        // Quizzes remain the shared assessment record, one per exam subject/section.
        Schema::table('quizzes', function (Blueprint $t) {
            $t->foreignId('exam_subject_id')->nullable()->constrained('exam_subjects')->restrictOnDelete();
            $t->unique(['exam_subject_id', 'section_id']);
        });
        Schema::table('quiz_results', function (Blueprint $t) {
            $t->decimal('theory_marks', 6, 2)->nullable();
            $t->decimal('practical_marks', 6, 2)->nullable();
            $t->timestamp('locked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('quiz_results', fn (Blueprint $t) => $t->dropColumn(['theory_marks', 'practical_marks', 'locked_at']));
        Schema::table('quizzes', function (Blueprint $t) {
            $t->dropUnique(['exam_subject_id', 'section_id']);
            $t->dropConstrainedForeignId('exam_subject_id');
        });
        Schema::dropIfExists('exam_subjects');
        Schema::dropIfExists('exam_classes');
        Schema::dropIfExists('exams');
        Schema::dropIfExists('exam_settings');
        Schema::table('grade_scales', fn (Blueprint $t) => $t->dropColumn('gpa'));
    }
};
