<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_types', function (Blueprint $t) {
            $t->decimal('annual_quota', 8, 2)->default(0);
            $t->boolean('requires_attachment')->default(false);
            $t->decimal('max_consecutive_days', 8, 2)->nullable();
            $t->unsignedInteger('min_notice_days')->default(0);
            $t->boolean('allow_half_day')->default(false);
            $t->boolean('allow_backdated')->default(false);
            $t->boolean('is_active')->default(true);
        });
        DB::table('leave_types')->update(['annual_quota' => DB::raw('annual_days')]);
        foreach (['Casual', 'Sick', 'Emergency'] as $name) {
            DB::table('leave_types')->insertOrIgnore(['name' => $name, 'annual_days' => 0, 'is_paid' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
        Schema::table('leave_requests', function (Blueprint $t) {
            $t->foreignId('session_id')->nullable()->constrained('academic_sessions');
            $t->date('from_date')->nullable();
            $t->date('to_date')->nullable();
            $t->string('half_day')->default('none');
            $t->decimal('days', 8, 2)->nullable();
            $t->string('attachment_path')->nullable();
            $t->string('contact_number')->nullable();
            $t->foreignId('decided_by')->nullable()->constrained('users');
            $t->timestamp('decided_at')->nullable();
            $t->text('admin_remark')->nullable();
            $t->boolean('cancel_requested')->default(false);
            $t->boolean('added_by_admin')->default(false);
            $t->index(['teacher_id', 'status']);
            $t->index(['from_date', 'to_date']);
        });
        DB::table('leave_requests')->update(['from_date' => DB::raw('start_date'), 'to_date' => DB::raw('end_date'), 'admin_remark' => DB::raw('remarks'), 'decided_at' => DB::raw('reviewed_at')]);
        Schema::create('leave_balances', function (Blueprint $t) {
            $t->id();
            $t->foreignId('teacher_id')->constrained();
            $t->foreignId('leave_type_id')->constrained();
            $t->foreignId('session_id')->constrained('academic_sessions');
            $t->decimal('total', 8, 2);
            $t->decimal('used', 8, 2)->default(0);
            $t->timestamps();
            $t->unique(['teacher_id', 'leave_type_id', 'session_id']);
        });
        Schema::create('leave_request_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('request_id')->constrained('leave_requests');
            $t->string('action');
            $t->foreignId('actor_id')->nullable()->constrained('users');
            $t->text('note')->nullable();
            $t->timestamp('created_at')->useCurrent();
        });
        Schema::create('leave_balance_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('balance_id')->constrained('leave_balances');
            $t->foreignId('actor_id')->constrained('users');
            $t->decimal('old_total', 8, 2);
            $t->decimal('new_total', 8, 2);
            $t->text('reason');
            $t->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_balance_logs');
        Schema::dropIfExists('leave_request_logs');
        Schema::dropIfExists('leave_balances');
        Schema::table('leave_requests', function (Blueprint $t) {
            $t->dropForeign(['session_id']);
            $t->dropForeign(['decided_by']);
            $t->dropIndex(['teacher_id', 'status']);
            $t->dropIndex(['from_date', 'to_date']);
            $t->dropColumn(['session_id', 'from_date', 'to_date', 'half_day', 'days', 'attachment_path', 'contact_number', 'decided_by', 'decided_at', 'admin_remark', 'cancel_requested', 'added_by_admin']);
        });
        Schema::table('leave_types', fn (Blueprint $t) => $t->dropColumn(['annual_quota', 'requires_attachment', 'max_consecutive_days', 'min_notice_days', 'allow_half_day', 'allow_backdated', 'is_active']));
    }
};
