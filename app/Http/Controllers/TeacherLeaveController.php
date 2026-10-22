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
        $request->validate([
            'month' => 'nullable|date_format:Y-m',
            'teacher' => 'nullable|exists:teachers,id',
            'teacher_id' => 'nullable|exists:teachers,id',
            'status' => 'nullable|string|max:50',
            'view' => 'nullable|in:list,calendar',
        ]);

        $month = Carbon::createFromFormat('!Y-m', $request->input('month', now()->format('Y-m')));
        $prevMonth = $month->copy()->subMonth()->format('Y-m');
        $nextMonth = $month->copy()->addMonth()->format('Y-m');
        $thisMonth = now()->format('Y-m');
        $dateRangeText = $month->copy()->startOfMonth()->format('j M Y') . ' – ' . $month->copy()->endOfMonth()->format('j M Y');

        // View preference: query string overrides session, else fallback to session, default 'list'
        $view = $request->input('view');
        if (! $view) {
            $view = session('teacher_leaves_view', 'list');
        } else {
            session(['teacher_leaves_view' => $view]);
        }

        // Support both teacher and teacher_id query parameters
        $teacherId = $request->input('teacher', $request->input('teacher_id'));
        $teacherId = ($teacherId !== null && $teacherId !== '') ? (int) $teacherId : null;

        // Status normalization (case-insensitive)
        $rawStatus = trim((string) $request->input('status', ''));
        $normalizedStatus = 'All';
        if ($rawStatus !== '' && strtolower($rawStatus) !== 'all') {
            $lower = strtolower($rawStatus);
            $normalizedStatus = match ($lower) {
                'pending' => 'Pending',
                'approved' => 'Approved',
                'rejected' => 'Rejected',
                'cancelled', 'canceled', 'cancel requested' => 'Cancelled',
                default => ucfirst($lower),
            };
        }

        $types = \Illuminate\Support\Facades\Cache::remember('active_leave_types', 300, fn () => LeaveType::where('is_active', true)->orderBy('name')->get());

        // Teachers: Alphabetical & Title Case names
        $teachers = Teacher::select('id', 'name')
            ->orderBy('name')
            ->get()
            ->map(function ($t) {
                $t->name = mb_convert_case(trim($t->name), MB_CASE_TITLE, 'UTF-8');
                return $t;
            })
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $selectedTeacher = $teacherId ? $teachers->firstWhere('id', $teacherId) : null;

        // Teacher leave balance summary for selected teacher
        $teacherBalancesSummary = [];
        $balances = [];
        if ($selectedTeacher) {
            foreach ($types as $type) {
                $rem = $service->balance((int) $selectedTeacher->id, $type, (int) $month->year);
                $balances[$type->id] = $rem;
                $isNoLimit = ! $type->is_paid || (float) $type->annual_days <= 0 || (float) $type->annual_quota <= 0;

                if ($isNoLimit) {
                    $text = 'No limit';
                } else {
                    $total = (int) $type->annual_days;
                    $text = "{$rem} left of {$total}";
                }

                $teacherBalancesSummary[] = [
                    'type' => $type->name,
                    'text' => $text,
                    'is_no_limit' => $isNoLimit,
                ];
            }
        }

        // Status counts for the selected month and teacher
        $statusCountBase = LeaveRequest::whereDate('start_date', '<=', $month->copy()->endOfMonth())
            ->whereDate('end_date', '>=', $month);
        if ($teacherId) {
            $statusCountBase->where('teacher_id', $teacherId);
        }

        $statusCounts = [
            'All' => (clone $statusCountBase)->count(),
            'Pending' => (clone $statusCountBase)->whereIn('status', ['Pending', 'pending'])->count(),
            'Approved' => (clone $statusCountBase)->whereIn('status', ['Approved', 'approved'])->count(),
            'Rejected' => (clone $statusCountBase)->whereIn('status', ['Rejected', 'rejected'])->count(),
            'Cancelled' => (clone $statusCountBase)->whereIn('status', ['Cancelled', 'cancelled', 'Cancel Requested'])->count(),
        ];

        // Query execution
        $query = LeaveRequest::with(['teacher:id,name', 'leaveType'])
            ->whereDate('start_date', '<=', $month->copy()->endOfMonth())
            ->whereDate('end_date', '>=', $month);

        if ($teacherId) {
            $query->where('teacher_id', $teacherId);
        }

        if ($normalizedStatus !== 'All' && in_array($normalizedStatus, ['Pending', 'Approved', 'Rejected', 'Cancelled'])) {
            if ($normalizedStatus === 'Cancelled') {
                $query->whereIn('status', ['Cancelled', 'cancelled', 'Cancel Requested']);
            } else {
                $query->where('status', $normalizedStatus);
            }
        }

        // Active filters breadcrumb / line
        $activeFiltersParts = [
            $month->format('F Y'),
            $selectedTeacher ? $selectedTeacher->name : 'All teachers',
            $normalizedStatus === 'All' ? 'All statuses' : $normalizedStatus,
            $view === 'calendar' ? 'Calendar view' : 'List view',
        ];
        $activeFiltersText = implode(' · ', $activeFiltersParts);

        return view('teachers.leaves', [
            'teachers' => $teachers,
            'selectedTeacherId' => $teacherId,
            'selectedTeacher' => $selectedTeacher,
            'types' => $types,
            'balances' => $balances,
            'teacherBalancesSummary' => $teacherBalancesSummary,
            'month' => $month,
            'prevMonth' => $prevMonth,
            'nextMonth' => $nextMonth,
            'thisMonth' => $thisMonth,
            'dateRangeText' => $dateRangeText,
            'view' => $view,
            'selectedStatus' => $normalizedStatus,
            'statusCounts' => $statusCounts,
            'activeFiltersText' => $activeFiltersText,
            'calendar' => (clone $query)->get(),
            'leaves' => $query->latest()->paginate(15)->withQueryString(),
        ]);
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
