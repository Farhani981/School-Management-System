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
    Schema::create('student_attendances', function (Blueprint $table) {
        $table->id();

        $table->foreignId('student_id')
              ->constrained('students')
              ->cascadeOnDelete();

        $table->date('attendance_date');

        $table->enum('status', [
            'Present',
            'Absent',
            'Late',
            'Leave'
        ]);

        $table->text('remarks')->nullable();

        $table->timestamps();

        $table->unique(['student_id', 'attendance_date']);
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_attendances');
    }
};
