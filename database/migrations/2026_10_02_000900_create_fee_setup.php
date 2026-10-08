<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_types', function (Blueprint $t) {
            $t->id();
            $t->string('name', 100)->unique();
            $t->string('frequency', 20);
            $t->boolean('is_active')->default(true);
            $t->softDeletes();
            $t->timestamps();
        });
        Schema::create('fee_structures', function (Blueprint $t) {
            $t->id();
            $t->foreignId('academic_session_id')->constrained();
            $t->foreignId('school_class_id')->constrained();
            $t->foreignId('fee_type_id')->constrained();
            $t->decimal('amount', 10, 2)->default(0);
            $t->timestamps();
            $t->unique(['academic_session_id', 'school_class_id', 'fee_type_id'], 'fee_structure_assignment_unique');
        });
        Schema::create('discounts', function (Blueprint $t) {
            $t->id();
            $t->string('name', 100)->unique();
            $t->string('type', 10);
            $t->decimal('value', 10, 2);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('student_discounts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('student_id')->constrained();
            $t->foreignId('discount_id')->constrained();
            $t->foreignId('fee_type_id')->nullable()->constrained();
            $t->date('valid_from');
            $t->date('valid_to')->nullable();
            $t->timestamps();
            $t->index(['student_id', 'valid_from', 'valid_to']);
        });
        Schema::create('student_fee_overrides', function (Blueprint $t) {
            $t->id();
            $t->foreignId('student_id')->constrained();
            $t->foreignId('fee_type_id')->constrained();
            $t->decimal('amount', 10, 2);
            $t->string('reason', 1000);
            $t->timestamps();
            $t->unique(['student_id', 'fee_type_id']);
        });
        Schema::create('fee_settings', function (Blueprint $t) {
            $t->unsignedTinyInteger('id')->primary();
            $t->unsignedTinyInteger('due_day')->default(10);
            $t->string('fine_type', 10)->default('none');
            $t->decimal('fine_amount', 10, 2)->default(0);
            $t->unsignedSmallInteger('grace_days')->default(0);
            $t->decimal('fine_max_cap', 10, 2)->default(0);
            $t->string('voucher_prefix', 10)->default('VCH');
            $t->string('receipt_prefix', 10)->default('RCP');
            $t->boolean('sibling_discount_enabled')->default(false);
            $t->string('sibling_discount_type', 10)->default('percent');
            $t->decimal('sibling_discount_value', 10, 2)->default(0);
            $t->timestamps();
        });
        DB::table('fee_settings')->insert(['id' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        foreach (['student_fee_overrides', 'student_discounts', 'fee_structures', 'fee_settings', 'discounts', 'fee_types'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
