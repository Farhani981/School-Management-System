<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('exam_participations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $t->foreignId('class_id')->constrained('school_classes')->restrictOnDelete();
            $t->foreignId('section_id')->constrained('school_sections')->restrictOnDelete();
            $t->unique(['exam_id', 'class_id', 'section_id']);
        });
        Schema::create('exam_subject_assignments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('exam_subject_id')->constrained()->cascadeOnDelete();
            $t->foreignId('section_id')->constrained('school_sections')->restrictOnDelete();
            $t->foreignId('teacher_id')->constrained('teachers')->restrictOnDelete();
            $t->unique(['exam_subject_id', 'section_id']);
        });
        // Adopt historical scheduled participation without changing existing results or statuses.
        DB::table('exam_schedules')->join('exam_subjects', 'exam_subjects.id', '=', 'exam_schedules.exam_subject_id')
            ->select('exam_subjects.exam_id', 'exam_subjects.class_id', 'exam_schedules.section_id', 'exam_schedules.teacher_id', 'exam_subjects.id as subject_config_id')
            ->orderBy('exam_schedules.id')->get()->each(function ($row) {
                DB::table('exam_participations')->insertOrIgnore(['exam_id'=>$row->exam_id, 'class_id'=>$row->class_id, 'section_id'=>$row->section_id]);
                if ($row->teacher_id) DB::table('exam_subject_assignments')->insertOrIgnore(['exam_subject_id'=>$row->subject_config_id, 'section_id'=>$row->section_id, 'teacher_id'=>$row->teacher_id]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_subject_assignments');
        Schema::dropIfExists('exam_participations');
    }
};
