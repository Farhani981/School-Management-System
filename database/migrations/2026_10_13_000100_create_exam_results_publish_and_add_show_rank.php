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
        if (! Schema::hasTable('exam_results_publish')) {
            Schema::create('exam_results_publish', function (Blueprint $table) {
                $table->id();
                $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
                $table->foreignId('class_id')->nullable()->constrained('school_classes')->cascadeOnDelete();
                $table->boolean('is_published')->default(true);
                $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('published_at')->nullable();
                $table->timestamps();

                $table->unique(['exam_id', 'class_id'], 'exam_class_publish_unique');
            });
        }

        if (Schema::hasTable('exam_settings') && ! Schema::hasColumn('exam_settings', 'show_rank')) {
            Schema::table('exam_settings', function (Blueprint $table) {
                $table->boolean('show_rank')->default(false)->after('overall_pass_percent');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('exam_settings') && Schema::hasColumn('exam_settings', 'show_rank')) {
            Schema::table('exam_settings', function (Blueprint $table) {
                $table->dropColumn('show_rank');
            });
        }

        Schema::dropIfExists('exam_results_publish');
    }
};
