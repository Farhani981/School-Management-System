<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('expenses_tables', function (Blueprint $table) {
            $table->string('title')->nullable();
            $table->string('paid_to')->nullable();
            $table->string('attachment_path')->nullable();
        });
        Schema::create('account_incomes', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('category', 100);
            $table->unsignedBigInteger('amount_cents');
            $table->date('income_date')->index();
            $table->string('payment_method', 50);
            $table->string('received_from');
            $table->text('description')->nullable();
            $table->string('reference', 150)->nullable();
            $table->string('attachment_path')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::table('payrolls', fn (Blueprint $table) => $table->string('payment_method', 50)->nullable());
    }

    public function down(): void
    {
        Schema::table('payrolls', fn (Blueprint $table) => $table->dropColumn('payment_method'));
        Schema::dropIfExists('account_incomes');
        Schema::table('expenses_tables', fn (Blueprint $table) => $table->dropColumn(['title', 'paid_to', 'attachment_path']));
    }
};
