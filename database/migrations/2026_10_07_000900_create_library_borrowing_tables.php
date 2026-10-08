<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_book_copies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained('library_books')->restrictOnDelete();
            $table->string('accession_number', 40)->nullable()->unique();
            $table->string('barcode', 40)->nullable()->unique();
            $table->string('shelf', 100)->nullable();
            $table->string('rack', 100)->nullable();
            $table->string('condition', 20)->default('Good');
            $table->string('status', 20)->default('Available')->index();
            $table->timestamps();
            $table->index(['book_id', 'status']);
        });
        Schema::create('library_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('library_members')->restrictOnDelete();
            $table->foreignId('book_id')->constrained('library_books')->restrictOnDelete();
            $table->foreignId('book_copy_id')->constrained('library_book_copies')->restrictOnDelete();
            // One open loan per physical copy, enforced even for concurrent requests.
            $table->foreignId('active_copy_id')->nullable()->unique()->constrained('library_book_copies')->restrictOnDelete();
            $table->date('issue_date');
            $table->date('due_date')->index();
            $table->date('return_date')->nullable();
            $table->string('status', 20)->default('Issued')->index();
            $table->string('return_condition', 20)->nullable();
            $table->text('notes')->nullable();
            $table->text('return_notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('returned_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['member_id', 'return_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_transactions');
        Schema::dropIfExists('library_book_copies');
    }
};
