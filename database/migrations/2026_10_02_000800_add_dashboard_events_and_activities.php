<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('academic_sessions', 'is_current')) {
            Schema::table('academic_sessions', fn (Blueprint $t) => $t->boolean('is_current')->default(false)->index());
            // Adopt the existing session matching this school year; preserve all records.
            DB::table('academic_sessions')->where('id', DB::table('academic_sessions')->whereIn('name', [now()->year.'-'.(now()->year + 1), now()->year.'-'.substr((string) (now()->year + 1), -2)])->orderByDesc('id')->value('id'))->update(['is_current' => true]);
        }
        Schema::create('events', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->text('description')->nullable();
            $t->date('date')->index();
            $t->date('end_date')->nullable();
            $t->string('type', 20)->default('event');
            $t->string('color', 20)->default('blue');
            $t->timestamps();
        });
        Schema::create('activities', function (Blueprint $t) {
            $t->id();
            $t->text('description');
            $t->string('type', 50)->index();
            $t->string('subject')->nullable();
            $t->unsignedBigInteger('user_id')->nullable()->index();
            $t->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
        Schema::dropIfExists('events');
        // Retain is_current: academic setup may already be using this shared field.
    }
};
