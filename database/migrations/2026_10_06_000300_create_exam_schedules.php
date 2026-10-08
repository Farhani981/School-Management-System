<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', fn (Blueprint $t) => $t->timestamp('datesheet_published_at')->nullable());
        Schema::create('exam_schedules', function (Blueprint $t) {
            $t->id();
            $t->foreignId('exam_subject_id')->constrained()->restrictOnDelete();
            $t->foreignId('section_id')->constrained('school_sections');
            $t->date('exam_date');
            $t->time('start_time');
            $t->time('end_time');
            $t->string('room', 100)->nullable();
            $t->foreignId('teacher_id')->nullable()->constrained('teachers');
            $t->foreignId('invigilator_id')->nullable()->constrained('teachers');
            $t->date('paper_deadline');
            $t->boolean('holiday_override')->default(false);
            $t->string('paper_status')->default('pending');
            $t->string('marks_status')->default('not_started');
            $t->text('return_reason')->nullable();
            $t->timestamps();
            $t->unique(['exam_subject_id', 'section_id']);
            $t->index(['exam_date', 'start_time']);
            $t->index(['teacher_id', 'exam_date']);
        });
        Schema::create('exam_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('exam_schedule_id')->constrained()->restrictOnDelete();
            $t->string('action');
            $t->foreignId('actor_id')->constrained('users');
            $t->text('note')->nullable();
            $t->json('old_value')->nullable();
            $t->json('new_value')->nullable();
            $t->timestamp('created_at')->useCurrent();
        });
        Schema::create('notifications', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('type');
            $t->morphs('notifiable');
            $t->text('data');
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('exam_logs');
        Schema::dropIfExists('exam_schedules');
        Schema::table('exams', fn (Blueprint $t) => $t->dropColumn('datesheet_published_at'));
    }
};
