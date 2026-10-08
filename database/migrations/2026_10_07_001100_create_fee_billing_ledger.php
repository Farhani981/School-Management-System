<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('fee_vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('number')->nullable()->unique();
            $table->string('active_billing_key')->nullable()->unique();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_session_id')->constrained()->restrictOnDelete();
            $table->foreignId('school_class_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('year'); $table->unsignedTinyInteger('month');
            $table->date('due_date');
            $table->string('student_name'); $table->string('admission_no'); $table->string('class_name');
            $table->unsignedBigInteger('subtotal_cents'); $table->unsignedBigInteger('discount_cents'); $table->unsignedBigInteger('total_cents');
            $table->string('fine_type')->default('none'); $table->unsignedBigInteger('fine_amount_cents')->default(0);
            $table->unsignedInteger('grace_days')->default(0); $table->unsignedBigInteger('fine_cap_cents')->nullable();
            $table->date('settled_on')->nullable();
            $table->timestamp('cancelled_at')->nullable(); $table->text('cancellation_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps(); $table->index(['year', 'month']);
        });
        Schema::create('fee_voucher_items', function (Blueprint $table) {
            $table->id(); $table->foreignId('fee_voucher_id')->constrained()->restrictOnDelete();
            $table->foreignId('fee_type_id')->constrained()->restrictOnDelete();
            $table->string('name'); $table->string('frequency');
            $table->unsignedBigInteger('amount_cents'); $table->unsignedBigInteger('discount_cents'); $table->unsignedBigInteger('net_cents');
            $table->text('override_reason')->nullable(); $table->timestamps();
        });
        Schema::create('fee_payments', function (Blueprint $table) {
            $table->id(); $table->foreignId('fee_voucher_id')->constrained()->restrictOnDelete();
            $table->string('number')->nullable()->unique(); $table->uuid('submission_token')->unique();
            $table->unsignedBigInteger('amount_cents'); $table->date('payment_date');
            $table->string('method'); $table->string('reference')->nullable(); $table->text('notes')->nullable();
            $table->unsignedBigInteger('fine_cents'); $table->unsignedBigInteger('balance_after_cents');
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('reversed_at')->nullable(); $table->text('reversal_reason')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps(); $table->index('payment_date');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('fee_payments'); Schema::dropIfExists('fee_voucher_items'); Schema::dropIfExists('fee_vouchers');
    }
};
