<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_attendances', function (Blueprint $t) {
            $t->string('status', 20)->change();
            $t->foreignId('academic_session_id')->nullable()->constrained('academic_sessions');
            $t->foreignId('school_class_id')->nullable()->constrained('school_classes');
            $t->foreignId('section_id')->nullable()->constrained('school_sections');
            $t->date('date')->nullable();
            $t->foreignId('marked_by')->nullable()->constrained('users');
            $t->unique(['student_id', 'date']);
            $t->index(['school_class_id', 'section_id', 'date'], 'student_attendance_class_date_index');
        });
        foreach (DB::table('student_attendances')->orderBy('id')->get() as $record) {
            $student = DB::table('students')->find($record->student_id);
            DB::table('student_attendances')->where('id', $record->id)->update(['date' => substr($record->attendance_date, 0, 10), 'status' => strtolower($record->status), 'academic_session_id' => $student?->academic_session_id, 'school_class_id' => $student?->class_id, 'section_id' => $student?->section_id]);
        }
        Schema::create('staff_attendances', function (Blueprint $t) {
            $t->id();
            $t->foreignId('teacher_id')->constrained('teachers');
            $t->date('date');
            $t->string('status', 20);
            $t->time('check_in')->nullable();
            $t->time('check_out')->nullable();
            $t->unsignedInteger('late_minutes')->default(0);
            $t->text('remarks')->nullable();
            $t->timestamps();
            $t->unique(['teacher_id', 'date']);
        });
        foreach (DB::table('teacher_attendances')->get() as $row) {
            DB::table('staff_attendances')->insert(['teacher_id' => $row->teacher_id, 'date' => substr($row->date, 0, 10), 'status' => strtolower($row->status), 'created_at' => $row->created_at, 'updated_at' => $row->updated_at]);
        }
        Schema::create('holidays', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->date('start_date');
            $t->date('end_date');
            $t->text('description')->nullable();
            $t->timestamps();
        });
        Schema::table('payrolls', fn (Blueprint $t) => $t->decimal('unpaid_days', 6, 2)->default(0)->change());
        if (! Schema::hasTable('jobs')) {
            Schema::create('jobs', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->string('queue')->index();
                $t->longText('payload');
                $t->unsignedTinyInteger('attempts');
                $t->unsignedInteger('reserved_at')->nullable();
                $t->unsignedInteger('available_at');
                $t->unsignedInteger('created_at');
            });
        }
        if (! Schema::hasTable('failed_jobs')) {
            Schema::create('failed_jobs', function (Blueprint $t) {
                $t->id();
                $t->string('uuid')->unique();
                $t->text('connection');
                $t->text('queue');
                $t->longText('payload');
                $t->longText('exception');
                $t->timestamp('failed_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('staff_attendances');
        foreach (DB::table('student_attendances')->get() as $row) {
            DB::table('student_attendances')->where('id', $row->id)->update(['status' => $row->status === 'half_day' ? 'Present' : ucfirst($row->status)]);
        }
        Schema::table('student_attendances', function (Blueprint $t) {
            $t->dropUnique(['student_id', 'date']);
            $t->dropIndex('student_attendance_class_date_index');
            foreach (['academic_session_id', 'school_class_id', 'section_id', 'marked_by'] as $field) {
                $t->dropConstrainedForeignId($field);
            }
            $t->dropColumn('date');
        });
    }
};
