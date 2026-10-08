<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('students')->whereNotNull('class_id')->select('class_id', 'roll_number')->groupBy('class_id', 'roll_number')->havingRaw('COUNT(*) > 1')->exists();
        if ($duplicates) throw new RuntimeException('Resolve duplicate roll numbers within each class before migrating. Archived records reserve their roll numbers.');
        Schema::create('admission_sequences', function (Blueprint $table) {
            $table->unsignedInteger('year')->primary();
            $table->unsignedBigInteger('last_number')->default(0);
        });
        foreach (DB::table('students')->pluck('admission_no') as $number) {
            if (preg_match('/^ADM-(\d{4})-(\d+)$/', $number, $match)) {
                DB::table('admission_sequences')->insertOrIgnore(['year' => $match[1], 'last_number' => 0]);
                DB::table('admission_sequences')->where('year', $match[1])->where('last_number', '<', (int) $match[2])->update(['last_number' => (int) $match[2]]);
            }
        }
        Schema::table('students', function (Blueprint $table) {
            $table->string('family_id', 100)->nullable()->index();
            $table->unique(['class_id', 'roll_number']);
            $table->string('status', 30)->default('Active')->change();
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropUnique(['class_id', 'roll_number']);
            $table->dropColumn('family_id');
        });
        Schema::dropIfExists('admission_sequences');
    }
};
