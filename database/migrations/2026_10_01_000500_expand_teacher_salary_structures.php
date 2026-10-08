<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salary_structures', function (Blueprint $table) {
            foreach (['house_allowance', 'medical_allowance', 'transport_allowance', 'other_allowance', 'fixed_deductions'] as $field) {
                $table->decimal($field, 10, 2)->default(0);
            }
            $table->string('bank_name')->nullable();
            $table->string('account_no', 100)->nullable();
        });
        DB::table('salary_structures')->update(['other_allowance' => DB::raw('allowances'), 'fixed_deductions' => DB::raw('deductions')]);
    }

    public function down(): void
    {
        DB::table('salary_structures')->update(['allowances' => DB::raw('house_allowance + medical_allowance + transport_allowance + other_allowance'), 'deductions' => DB::raw('fixed_deductions')]);
        Schema::table('salary_structures', fn (Blueprint $table) => $table->dropColumn(['house_allowance', 'medical_allowance', 'transport_allowance', 'other_allowance', 'fixed_deductions', 'bank_name', 'account_no']));
    }
};
