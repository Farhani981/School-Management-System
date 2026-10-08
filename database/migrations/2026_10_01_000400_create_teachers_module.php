<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $t) {
                $t->id();
                $t->string('name');
                $t->string('email')->unique();
                $t->timestamp('email_verified_at')->nullable();
                $t->string('password');
                $t->rememberToken();
                $t->timestamps();
            });
        }
        if (! Schema::hasColumn('users', 'role')) {
            Schema::table('users', fn (Blueprint $t) => $t->string('role')->default('User'));
        }
        foreach (['departments', 'designations', 'subjects'] as $name) {
            Schema::create($name, function (Blueprint $t) {
                $t->id();
                $t->string('name')->unique();
                $t->timestamps();
            });
        }
        Schema::create('teacher_sequences', function (Blueprint $t) {
            $t->unsignedInteger('year')->primary();
            $t->unsignedInteger('number')->default(0);
        });
        Schema::create('teachers', function (Blueprint $t) {
            $t->id();
            $t->string('employee_id')->unique();
            $t->string('name');
            $t->string('cnic')->unique();
            $t->string('gender');
            $t->date('date_of_birth')->nullable();
            $t->string('phone');
            $t->string('email')->nullable();
            $t->text('address')->nullable();
            $t->string('emergency_contact')->nullable();
            $t->foreignId('department_id')->constrained();
            $t->foreignId('designation_id')->constrained();
            $t->date('joining_date');
            $t->string('status')->default('Active');
            $t->text('qualification')->nullable();
            $t->text('experience')->nullable();
            $t->string('photo')->nullable();
            $t->foreignId('user_id')->nullable()->unique()->constrained();
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('teacher_documents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('path');
            $t->timestamps();
        });
        Schema::create('teacher_assignments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('teacher_id')->constrained();
            $t->foreignId('class_id')->constrained('school_classes');
            $t->foreignId('section_id')->constrained('school_sections');
            $t->foreignId('subject_id')->constrained('subjects');
            $t->foreignId('session_id')->constrained('academic_sessions');
            $t->boolean('is_class_teacher')->default(false);
            $t->unsignedInteger('weekly_periods')->default(1);
            $t->timestamps();
            $t->unique(['class_id', 'section_id', 'subject_id', 'session_id'], 'assignment_subject_unique');
        });
        Schema::create('leave_types', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->unsignedInteger('annual_days');
            $t->boolean('is_paid')->default(true);
            $t->timestamps();
        });
        Schema::create('leave_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('teacher_id')->constrained();
            $t->foreignId('leave_type_id')->constrained();
            $t->date('start_date');
            $t->date('end_date');
            $t->text('reason');
            $t->string('status')->default('Pending');
            $t->text('remarks')->nullable();
            $t->timestamp('reviewed_at')->nullable();
            $t->timestamps();
        });
        Schema::create('salary_structures', function (Blueprint $t) {
            $t->id();
            $t->foreignId('teacher_id')->unique()->constrained();
            $t->decimal('basic_salary', 12, 2);
            $t->decimal('allowances', 12, 2)->default(0);
            $t->decimal('deductions', 12, 2)->default(0);
            $t->timestamps();
        });
        Schema::create('teacher_attendances', function (Blueprint $t) {
            $t->id();
            $t->foreignId('teacher_id')->constrained();
            $t->date('date');
            $t->string('status');
            $t->timestamps();
            $t->unique(['teacher_id', 'date']);
        });
        Schema::create('teacher_advances', function (Blueprint $t) {
            $t->id();
            $t->foreignId('teacher_id')->constrained();
            $t->date('month');
            $t->decimal('amount', 12, 2);
            $t->text('notes')->nullable();
            $t->timestamps();
        });
        Schema::create('payrolls', function (Blueprint $t) {
            $t->id();
            $t->foreignId('teacher_id')->constrained();
            $t->date('month');
            foreach (['basic_salary', 'allowances', 'deductions', 'absence_deduction', 'advances', 'net_salary'] as $field) {
                $t->decimal($field, 12, 2)->default(0);
            } $t->unsignedInteger('unpaid_days')->default(0);
            $t->string('status')->default('Pending');
            $t->timestamp('paid_at')->nullable();
            $t->timestamps();
            $t->unique(['teacher_id', 'month']);
        });
        DB::table('leave_types')->insert([
            ['name' => 'Casual', 'annual_days' => 12, 'is_paid' => true], ['name' => 'Sick', 'annual_days' => 10, 'is_paid' => true], ['name' => 'Unpaid', 'annual_days' => 365, 'is_paid' => false],
        ]);
    }

    public function down(): void
    {
        foreach (['payrolls', 'teacher_advances', 'teacher_attendances', 'salary_structures', 'leave_requests', 'leave_types', 'teacher_assignments', 'teacher_documents', 'teachers', 'teacher_sequences', 'subjects', 'designations', 'departments'] as $name) {
            Schema::dropIfExists($name);
        }
        // Users may predate this module; preserve accounts and their role column.
    }
};
