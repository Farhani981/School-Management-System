<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_members', function (Blueprint $table) {
            $table->id();
            $table->string('member_id', 40)->nullable()->unique();
            $table->string('member_type', 20)->index();
            $table->foreignId('student_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('teacher_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->date('registration_date');
            $table->unsignedSmallInteger('borrowing_limit');
            $table->string('status', 20)->default('Active')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_members');
    }
};
