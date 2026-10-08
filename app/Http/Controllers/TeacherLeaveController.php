<?php

namespace App\Http\Controllers;

use App\Http\Requests\LeaveRequestForm;
use App\Http\Requests\TeacherActionRequest;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Teacher;
use App\Services\TeacherLeaves;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TeacherLeaveController extends Controller
{
    public function index(Request $request, TeacherLeaves $service)
    {
        $request->validate(['month' => 'nullable|date_format:Y-m', 'teacher_id' => 'nullable|exists:teachers,id', 'status' => 'nullable|in:Pending,Approved,Rejected', 'view' => 'nullable|in:list,calendar']);
        $month = Carbon::createFromFormat('!Y-m', $request->input('month', now()->format('Y-m')));
        $types = LeaveType::orderBy('name')->get();
        $balances = [];
        if ($request->filled('teacher_id')) {
            foreach ($types as $type) {
                $balances[$type->id] = $service->balance((int) $request->teacher_id, $type, $month->year);
            }
        }
        $query = LeaveRequest::with(['teacher', 'leaveType'])->whereDate('start_date', '<=', $month->copy()->endOfMonth())->whereDate('end_date', '>=', $month);
        foreach (['teacher_id', 'status'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->input($field));
            }
        }

        return view('teachers.leaves', ['teachers' => Teacher::orderBy('name')->get(), 'types' => $types, 'balances' => $balances, 'month' => $month, 'calendar' => (clone $query)->get(), 'leaves' => $query->latest()->paginate(15)->withQueryString()]);
    }

    public function store(LeaveRequestForm $request, TeacherLeaves $service)
    {
        $service->apply($request->validated());

        return back()->with('success', 'Leave request submitted.');
    }

    public function review(TeacherActionRequest $request, LeaveRequest $leave, TeacherLeaves $service)
    {
        DB::transaction(function () use ($request, $leave, $service) {
            Teacher::withTrashed()->whereKey($leave->teacher_id)->lockForUpdate()->firstOrFail();
            $leave = LeaveRequest::whereKey($leave->id)->lockForUpdate()->firstOrFail();
            if ($leave->status !== 'Pending') {
                throw ValidationException::withMessages(['status' => 'This request has already been reviewed.']);
            }
            $service->assertUnprocessed($leave->teacher_id, $leave->start_date->toDateString(), $leave->end_date->toDateString());
            $leave->update($request->validated() + ['reviewed_at' => now()]);
        }, 3);

        return back()->with('success','Leave request reviewed.');
    }
}
