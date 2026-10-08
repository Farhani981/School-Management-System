<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Teacher;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeaveBalances
{
    public function pending(int $teacher, int $type, int $session): float
    {
        return (float) LeaveRequest::where('teacher_id', $teacher)->where('leave_type_id', $type)->where('session_id', $session)->whereIn('status', ['pending', 'Pending'])->sum('days');
    }

    public function initialize(int $session, ?int $source, int $actor): int
    {
        return DB::transaction(function () use ($session, $source, $actor) {
            AcademicSession::whereKey($session)->lockForUpdate()->firstOrFail();
            if ($source === $session) {
                throw ValidationException::withMessages(['source_session_id' => 'Choose a different carry-forward session.']);
            }
            if (LeaveRequest::where('session_id', $session)->exists()) {
                throw ValidationException::withMessages(['session_id' => 'This session already has requests. Reconcile balances before initializing it.']);
            }
            $previous = $source ? LeaveBalance::where('session_id', $source)->get()->keyBy(fn ($b) => $b->teacher_id.':'.$b->leave_type_id) : collect();
            $reserved = $source ? LeaveRequest::where('session_id', $source)->whereIn('status', ['pending', 'Pending'])->select('teacher_id', 'leave_type_id')->selectRaw('SUM(days) AS reserved')->groupBy('teacher_id', 'leave_type_id')->get()->keyBy(fn ($r) => $r->teacher_id.':'.$r->leave_type_id) : collect();
            $types = LeaveType::where('is_active', true)->get();
            $count = 0;
            foreach (Teacher::where('status', '!=', 'Inactive')->orderBy('id')->cursor() as $teacher) {
                foreach ($types as $type) {
                    $key = $teacher->id.':'.$type->id;
                    $old = $previous->get($key);
                    $carry = $old && $type->is_paid && (float) $type->annual_quota > 0 ? max(0, (float) $old->total - (float) $old->used - (float) ($reserved->get($key)?->reserved ?? 0)) : 0;
                    $balance = LeaveBalance::firstOrCreate(['teacher_id' => $teacher->id, 'leave_type_id' => $type->id, 'session_id' => $session], ['total' => (float) $type->annual_quota + $carry, 'used' => 0]);
                    if ($balance->wasRecentlyCreated) {
                        DB::table('leave_balance_logs')->insert(['balance_id' => $balance->id, 'actor_id' => $actor, 'old_total' => 0, 'new_total' => $balance->total, 'reason' => $source ? "Session initialization; carry-forward from session {$source}: {$carry} days" : 'Fresh session initialization', 'created_at' => now()]);
                        $count++;
                    }
                }
            }

            return $count;
        }, 3);
    }

    public function adjust(LeaveBalance $balance, float $total, string $reason, int $actor): void
    {
        DB::transaction(function () use ($balance, $total, $reason, $actor) {
            Teacher::withTrashed()->whereKey($balance->teacher_id)->lockForUpdate()->firstOrFail();
            $balance = LeaveBalance::whereKey($balance->id)->lockForUpdate()->firstOrFail();
            if ($total < (float) $balance->used + $this->pending($balance->teacher_id, $balance->leave_type_id, $balance->session_id)) {
                throw ValidationException::withMessages(['total' => 'Total cannot be below used days plus pending reservations.']);
            }
            DB::table('leave_balance_logs')->insert(['balance_id' => $balance->id, 'actor_id' => $actor, 'old_total' => $balance->total, 'new_total' => $total, 'reason' => $reason, 'created_at' => now()]);
            $balance->update(['total' => $total]);
        }, 3);
    }
}
