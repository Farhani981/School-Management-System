<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_applications', function (Blueprint $table) {
            $table->id();
            $table->string('applicant_name');
            $table->string('father_name');
            $table->string('phone', 30);
            $table->foreignId('applying_for_class_id')->constrained('school_classes');
            $table->string('previous_school')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('student_id')->nullable()->unique()->constrained('students')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            foreach (['from', 'to'] as $prefix) {
                $table->foreignId($prefix.'_session_id')->nullable()->constrained('academic_sessions');
                $table->foreignId($prefix.'_class_id')->nullable()->constrained('school_classes');
                $table->foreignId($prefix.'_section_id')->nullable()->constrained('school_sections');
            }
            $table->string('action', 20)->index();
            $table->unsignedBigInteger('promoted_by')->nullable();
            $table->date('date')->index();
            $table->timestamps();
        });
        Schema::create('roll_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('academic_sessions');
            $table->foreignId('class_id')->constrained('school_classes');
            $table->foreignId('section_id')->constrained('school_sections');
            $table->unsignedBigInteger('last_number')->default(0);
            $table->unique(['session_id', 'class_id', 'section_id']);
        });
        Schema::table('students', function (Blueprint $table) {
            $table->dropUnique(['class_id', 'roll_number']);
            $table->unique(['academic_session_id', 'class_id', 'section_id', 'roll_number'], 'students_assignment_roll_unique');
        });
    }

    public function down(): void
    {
        if (DB::table('students')->whereNotNull('class_id')->select('class_id', 'roll_number')->groupBy('class_id', 'roll_number')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Cannot restore the old class-wide roll index while different sections or sessions share roll numbers.');
        }
        Schema::table('students', function (Blueprint $table) {
            $table->dropUnique('students_assignment_roll_unique');
            $table->unique(['class_id', 'roll_number']);
        });
        Schema::dropIfExists('roll_sequences');
        Schema::dropIfExists('promotions');
        Schema::dropIfExists('admission_applications');
    }
};
