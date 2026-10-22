<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'phone')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('phone')->nullable()->index()->after('email');
            });
        }

        if (! Schema::hasTable('parent_student')) {
            Schema::create('parent_student', function (Blueprint $table) {
                $table->id();
                $table->foreignId('parent_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
                $table->string('relationship')->nullable();
                $table->boolean('is_primary')->default(true);
                $table->timestamps();

                $table->unique(['parent_id', 'student_id']);
            });
        } else {
            if (! Schema::hasColumn('parent_student', 'parent_id')) {
                Schema::table('parent_student', function (Blueprint $table) {
                    $table->foreignId('parent_id')->nullable()->after('student_id')->constrained('users')->cascadeOnDelete();
                });
                if (Schema::hasColumn('parent_student', 'user_id')) {
                    DB::statement('UPDATE parent_student SET parent_id = user_id WHERE parent_id IS NULL AND user_id IS NOT NULL');
                }
            }

            if (! Schema::hasColumn('parent_student', 'relationship')) {
                Schema::table('parent_student', function (Blueprint $table) {
                    $table->string('relationship')->nullable()->after('student_id');
                });
            }

            if (! Schema::hasColumn('parent_student', 'is_primary')) {
                Schema::table('parent_student', function (Blueprint $table) {
                    $table->boolean('is_primary')->default(true)->after('relationship');
                });
            }

            if (Schema::hasColumn('parent_student', 'verified_by') && DB::getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE parent_student MODIFY verified_by BIGINT UNSIGNED NULL');
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('parent_student')) {
            if (Schema::hasColumn('parent_student', 'parent_id') && Schema::hasColumn('parent_student', 'user_id')) {
                Schema::table('parent_student', function (Blueprint $table) {
                    $table->dropForeign(['parent_id']);
                    $table->dropColumn(['parent_id', 'relationship', 'is_primary']);
                });
            } else {
                Schema::dropIfExists('parent_student');
            }
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'phone')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('phone');
            });
        }
    }
};
