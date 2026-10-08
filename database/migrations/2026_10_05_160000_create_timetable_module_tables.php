<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periods', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_break')->default(false);
            $table->unsignedInteger('sort_order')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('timetable_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('school_classes')->cascadeOnDelete();
            $table->foreignId('section_id')->constrained('school_sections')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week'); // 1 = Mon, 2 = Tue, ..., 7 = Sun
            $table->foreignId('period_id')->constrained('periods')->cascadeOnDelete();
            $table->string('room', 50)->nullable();
            $table->string('status', 20)->default('draft'); // draft, published
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['session_id', 'section_id', 'day_of_week', 'period_id'], 'tt_section_slot_unique');
            $table->unique(['session_id', 'teacher_id', 'day_of_week', 'period_id'], 'tt_teacher_slot_unique');
            $table->index(['session_id', 'class_id', 'section_id']);
            $table->index(['session_id', 'teacher_id', 'day_of_week']);
            $table->index(['status', 'day_of_week']);
        });

        Schema::create('timetable_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entry_id')->constrained('timetable_entries')->cascadeOnDelete();
            $table->date('date');
            $table->string('type', 30); // substitute, cancelled, swapped
            $table->foreignId('substitute_teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['entry_id', 'date'], 'tt_override_unique');
            $table->index(['date', 'substitute_teacher_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetable_overrides');
        Schema::dropIfExists('timetable_entries');
        Schema::dropIfExists('periods');
    }
};
