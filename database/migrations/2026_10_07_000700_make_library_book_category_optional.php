<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Retain existing associations while allowing the simplified book form.
        Schema::table('library_books', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('library_books')->whereNull('category_id')->exists()) {
            throw new RuntimeException('Cannot restore required categories while uncategorized books exist.');
        }

        Schema::table('library_books', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable(false)->change();
        });
    }
};
