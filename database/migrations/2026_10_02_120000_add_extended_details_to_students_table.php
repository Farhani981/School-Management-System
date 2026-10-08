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
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email')->nullable();
            $table->string('previous_school')->nullable();
            $table->string('last_class')->nullable();
            $table->text('leaving_reason')->nullable();
            $table->string('blood_group', 20)->nullable();
            $table->text('allergies')->nullable();
            $table->string('height', 50)->nullable();
            $table->string('weight', 50)->nullable();
            $table->text('remarks')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn([
                'email',
                'previous_school',
                'last_class',
                'leaving_reason',
                'blood_group',
                'allergies',
                'height',
                'weight',
                'remarks',
            ]);
        });
    }
};
