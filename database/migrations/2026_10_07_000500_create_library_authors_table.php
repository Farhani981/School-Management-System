<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_authors', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->index();
            $table->text('biography')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('status', 20)->default('Active')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_authors');
    }
};
