<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {

            $table->string('admission_no')->unique();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('father_name');

            $table->date('date_of_birth');
            $table->enum('gender', ['Male', 'Female', 'Other']);

            $table->string('phone');
            $table->text('address');

            $table->date('admission_date');

            $table->unsignedBigInteger('class_id')->nullable();
            $table->unsignedBigInteger('section_id')->nullable();

            $table->string('roll_number');
            $table->string('photo')->nullable();

            $table->enum('status', [
                'Active',
                'Inactive',
                'Graduated',
                'Left School',
            ])->default('Active');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropUnique(['admission_no']);
            $table->dropColumn([
                'admission_no', 'first_name', 'last_name', 'father_name',
                'date_of_birth', 'gender', 'phone', 'address', 'admission_date',
                'class_id', 'section_id', 'roll_number', 'photo', 'status',
            ]);
        });
    }
};
