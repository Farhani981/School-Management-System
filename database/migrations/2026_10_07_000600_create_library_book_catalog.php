<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_publishers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            $table->string('contact_person')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->text('address')->nullable();
            $table->string('website')->nullable();
            $table->string('status', 20)->default('Active')->index();
            $table->timestamps();
        });
        Schema::create('library_books', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('library_categories')->restrictOnDelete();
            $table->foreignId('author_id')->constrained('library_authors')->restrictOnDelete();
            $table->foreignId('publisher_id')->nullable()->constrained('library_publishers')->restrictOnDelete();
            $table->string('title')->index();
            $table->string('isbn', 30)->nullable()->unique();
            $table->string('edition', 100)->nullable();
            $table->unsignedSmallInteger('publication_year')->nullable();
            $table->string('language', 100)->nullable();
            $table->unsignedInteger('pages')->nullable();
            $table->text('description')->nullable();
            $table->string('cover')->nullable();
            $table->string('shelf', 100)->nullable();
            $table->string('rack', 100)->nullable();
            $table->string('location')->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->date('purchase_date')->nullable();
            $table->string('supplier')->nullable();
            $table->string('status', 20)->default('Active')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_books');
        Schema::dropIfExists('library_publishers');
    }
};
