<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->string('code', 50)->nullable()->unique();
            $table->string('type')->default('theory');
            $table->string('category')->default('compulsory');
            $table->text('description')->nullable();
            $table->string('status')->default('Active');
            $table->softDeletes();
        });
        DB::table('subjects')->orderBy('id')->each(fn ($subject) => DB::table('subjects')->where('id', $subject->id)->update(['code' => sprintf('SUB-%04d', $subject->id)]));
        Schema::create('class_subject', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_class_id')->constrained('school_classes');
            $table->foreignId('subject_id')->constrained('subjects');
            $table->decimal('total_marks', 10, 2)->default(100);
            $table->decimal('pass_marks', 10, 2)->default(33);
            $table->decimal('practical_marks', 10, 2)->default(0);
            $table->boolean('is_compulsory')->default(true);
            $table->unsignedInteger('display_order')->default(1);
            $table->timestamps();
            $table->unique(['school_class_id', 'subject_id']);
        });
        // Preserve class links already implied by teacher assignments.
        foreach (DB::table('teacher_assignments')->select('class_id', 'subject_id')->distinct()->get() as $assignment) {
            DB::table('class_subject')->insert(['school_class_id' => $assignment->class_id, 'subject_id' => $assignment->subject_id, 'created_at' => now(), 'updated_at' => now()]);
        }
        // Foundation for future exams; this project has no marks table yet.
        Schema::create('subject_marks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained('subjects');
            $table->foreignId('school_class_id')->constrained('school_classes');
            $table->foreignId('student_id')->constrained('students');
            $table->string('assessment');
            $table->decimal('marks', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subject_marks');
        Schema::dropIfExists('class_subject');
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn(['code', 'type', 'category', 'description', 'status', 'deleted_at']);
        });
    }
};
