<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach (['school_classes', 'school_sections', 'academic_sessions'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->timestamps();
            });
        }
        // Preserve existing class and section assignments without inventing school data.
        foreach (['class_id' => 'school_classes', 'section_id' => 'school_sections'] as $field => $table) {
            foreach (DB::table('students')->whereNotNull($field)->distinct()->pluck($field) as $id) {
                DB::table($table)->insert(['id' => $id, 'name' => ($field === 'class_id' ? 'Class ' : 'Section ').$id]);
            }
        }
        Schema::table('students', function (Blueprint $table) {
            foreach (['b_form_number', 'mother_name', 'guardian_name', 'guardian_relationship', 'guardian_phone', 'emergency_contact'] as $field) {
                $table->string($field)->nullable();
            }
            $table->text('permanent_address')->nullable();
            $table->foreignId('academic_session_id')->nullable()->constrained('academic_sessions');
        });
        Schema::create('student_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('path');
            $table->timestamps();
        });
        Schema::create('student_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('event');
            $table->json('changes')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_histories');
        Schema::dropIfExists('student_documents');
        Schema::table('students', function (Blueprint $table) {
            $table->dropConstrainedForeignId('academic_session_id');
            $table->dropColumn(['b_form_number', 'mother_name', 'guardian_name', 'guardian_relationship', 'guardian_phone', 'emergency_contact', 'permanent_address']);
        });
        foreach (['academic_sessions', 'school_sections', 'school_classes'] as $name) {
            Schema::dropIfExists($name);
        }
    }
};
