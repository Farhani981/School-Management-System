<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('timetable_entries', function (Blueprint $table) {
            $table->dropUnique('tt_section_slot_unique');
            $table->unique(['session_id', 'class_id', 'section_id', 'day_of_week', 'period_id'], 'tt_class_section_slot_unique');
        });
    }

    public function down(): void
    {
        Schema::table('timetable_entries', function (Blueprint $table) {
            $table->dropUnique('tt_class_section_slot_unique');
            $table->unique(['session_id', 'section_id', 'day_of_week', 'period_id'], 'tt_section_slot_unique');
        });
    }
};
