<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses_tables', function (Blueprint $table) {
            $table->string('category', 100);
            $table->text('description');
            $table->unsignedBigInteger('amount_cents');
            $table->date('expense_date')->index();
            $table->string('payment_method', 50);
            $table->string('reference', 150)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('expenses_tables', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropIndex(['expense_date']);
            $table->dropColumn([
                'category', 'description', 'amount_cents', 'expense_date',
                'payment_method', 'reference', 'created_by',
            ]);
        });
    }
};
