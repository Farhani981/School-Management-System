<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\LeaveBalances;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LeaveSetupController extends Controller
{
    public function types()
    {
        return view('leaves.types', ['types' => LeaveType::withCount('requests')->orderBy('name')->get()]);
    }

    public function saveType(Request $request, ?LeaveType $type = null)
    {
        $request->merge(['name' => trim((string) $request->input('name'))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('leave_types')->ignore($type?->id)],
            'annual_quota' => ['required', 'numeric', 'min:0', 'max:9999', 'multiple_of:0.5'],
            'max_consecutive_days' => ['nullable', 'numeric', 'min:0.5', 'max:9999', 'multiple_of:0.5'],
            'min_notice_days' => ['required', 'integer', 'min:0', 'max:365'],
            'is_paid' => ['sometimes', 'boolean'], 'requires_attachment' => ['sometimes', 'boolean'],
            'allow_half_day' => ['sometimes', 'boolean'], 'allow_backdated' => ['sometimes', 'boolean'], 'is_active' => ['sometimes', 'boolean'],
        ]);
        foreach (['is_paid', 'requires_attachment', 'allow_half_day', 'allow_backdated', 'is_active'] as $key) {
            $data[$key] = $request->boolean($key);
        }
        $data['annual_days'] = (int) floor($data['annual_quota']);
        DB::transaction(fn () => $type ? $type->update($data) : LeaveType::create($data));

        return back()->with('success', 'Leave type saved. Existing session totals are unchanged; adjust them separately.');
    }

    public function balances(Request $request)
    {
        $request->validate(['session_id' => ['nullable', 'exists:academic_sessions,id'], 'teacher_id' => ['nullable', 'exists:teachers,id']]);
        $sessions = AcademicSession::orderByDesc('id')->get();
        $sessionId = $request->input('session_id', $sessions->firstWhere('is_current', true)?->id ?? $sessions->first()?->id);
        $pending = LeaveRequest::select('teacher_id', 'leave_type_id', 'session_id')->selectRaw('SUM(days) AS reserved')->where('session_id', $sessionId)->whereIn('status', ['pending', 'Pending'])->groupBy('teacher_id', 'leave_type_id', 'session_id');
        $balances = LeaveBalance::with(['teacher', 'leaveType'])->leftJoinSub($pending, 'reservations', function ($join) {
            $join->on('leave_balances.teacher_id', '=', 'reservations.teacher_id')->on('leave_balances.leave_type_id', '=', 'reservations.leave_type_id')->on('leave_balances.session_id', '=', 'reservations.session_id');
        })->select('leave_balances.*')->selectRaw('COALESCE(reservations.reserved, 0) AS pending')->where('leave_balances.session_id', $sessionId)->when($request->filled('teacher_id'), fn ($q) => $q->where('leave_balances.teacher_id', $request->teacher_id))->orderBy('leave_balances.teacher_id')->paginate(20)->withQueryString();
        $logs = DB::table('leave_balance_logs')->join('leave_balances', 'leave_balances.id', '=', 'leave_balance_logs.balance_id')->join('users', 'users.id', '=', 'leave_balance_logs.actor_id')->join('teachers', 'teachers.id', '=', 'leave_balances.teacher_id')->join('leave_types', 'leave_types.id', '=', 'leave_balances.leave_type_id')->where('leave_balances.session_id', $sessionId)->select('leave_balance_logs.*', 'users.name as actor', 'teachers.name as teacher', 'leave_types.name as type')->orderByDesc('leave_balance_logs.id')->limit(30)->get();

        return view('leaves.balances', compact('sessions', 'sessionId', 'balances', 'logs'));
    }

    public function initialize(Request $request, LeaveBalances $service)
    {
        $data = $request->validate(['session_id' => ['required', 'exists:academic_sessions,id'], 'source_session_id' => ['nullable', 'exists:academic_sessions,id', 'different:session_id']]);
        $count = $service->initialize((int) $data['session_id'], isset($data['source_session_id']) ? (int) $data['source_session_id'] : null, $request->user()->id);

        return back()->with('success', "{$count} balances created. Existing balances were preserved.");
    }

    public function adjust(Request $request, LeaveBalance $balance, LeaveBalances $service)
    {
        $request->merge(['reason' => trim((string) $request->input('reason'))]);
        $data = $request->validate(['total' => ['required', 'numeric', 'min:0', 'max:999999', 'multiple_of:0.5'], 'reason' => ['required', 'string', 'max:2000']]);
        $service->adjust($balance, (float) $data['total'], $data['reason'], $request->user()->id);

        return back()->with('success', 'Leave balance adjusted and logged.');
    }
}
