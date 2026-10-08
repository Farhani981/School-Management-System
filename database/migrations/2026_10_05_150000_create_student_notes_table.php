<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->foreignId('session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->text('note');
            $table->string('tag', 50)->default('Other');
            $table->date('follow_up_date')->nullable();
            $table->boolean('is_done')->default(false);
            $table->timestamps();

            $table->index(['student_id', 'session_id']);
            $table->index(['teacher_id', 'follow_up_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_notes');
    }
};
