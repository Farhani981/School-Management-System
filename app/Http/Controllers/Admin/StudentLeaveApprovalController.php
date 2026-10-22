<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\StudentLeaveRequest;
use App\Models\TeacherAssignment;
use App\Services\StudentLeaveService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StudentLeaveApprovalController extends Controller
{
    /**
     * List student leave requests with filters for status, class, and date.
     * Admin/Principal sees all requests.
     * Teachers only see requests belonging to their assigned classes.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $isAdmin = $user->hasAnyRole(['Admin', 'Principal']);
        $isTeacher = $user->hasRole('Teacher') && $user->teacher && $user->teacher->status === 'Active';

        if (! $isAdmin && ! $isTeacher) {
            abort(403, 'Unauthorized access to student leave requests.');
        }

        $query = StudentLeaveRequest::with(['student.schoolClass', 'student.section', 'reviewer'])
            ->latest();

        // Scope to teacher's assigned classes if teacher
        $assignedClassIds = collect();
        if (! $isAdmin && $isTeacher) {
            $assignedClassIds = TeacherAssignment::where('teacher_id', $user->teacher->id)
                ->pluck('class_id')
                ->filter()
                ->unique();

            $query->whereHas('student', function ($q) use ($assignedClassIds) {
                $q->whereIn('class_id', $assignedClassIds);
            });
        }

        // Filter: status
        if ($request->filled('status') && in_array($request->status, StudentLeaveRequest::STATUSES)) {
            $query->where('status', $request->status);
        }

        // Filter: class
        if ($request->filled('class_id')) {
            $classId = (int) $request->class_id;
            // If teacher, ensure they are assigned to this class
            if (! $isAdmin && ! $assignedClassIds->contains($classId)) {
                abort(403, 'You are not assigned to this class.');
            }
            $query->whereHas('student', fn ($q) => $q->where('class_id', $classId));
        }

        // Filter: date (requests overlapping the chosen date)
        if ($request->filled('date')) {
            $date = Carbon::parse($request->date)->toDateString();
            $query->where('from_date', '<=', $date)
                ->where('to_date', '>=', $date);
        }

        // Filter: student search
        if ($request->filled('search')) {
            $term = trim($request->search);
            $query->whereHas('student', function ($q) use ($term) {
                $q->where('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%")
                    ->orWhere('admission_no', 'like', "%{$term}%")
                    ->orWhere('roll_number', 'like', "%{$term}%");
            });
        }

        $leaves = $query->paginate(15)->withQueryString();

        // Available classes for dropdown
        $classes = $isAdmin
            ? SchoolClass::orderBy('name')->get()
            : SchoolClass::whereIn('id', $assignedClassIds)->orderBy('name')->get();

        // Status counts for cards
        $countQuery = StudentLeaveRequest::query();
        if (! $isAdmin && $isTeacher) {
            $countQuery->whereHas('student', fn ($q) => $q->whereIn('class_id', $assignedClassIds));
        }
        $pendingCount = (clone $countQuery)->where('status', StudentLeaveRequest::STATUS_PENDING)->count();
        $approvedCount = (clone $countQuery)->where('status', StudentLeaveRequest::STATUS_APPROVED)->count();
        $rejectedCount = (clone $countQuery)->where('status', StudentLeaveRequest::STATUS_REJECTED)->count();

        $layout = $isAdmin ? 'layouts.admin' : 'layouts.teacher';

        return view('admin.leaves.students', compact(
            'leaves',
            'classes',
            'pendingCount',
            'approvedCount',
            'rejectedCount',
            'isAdmin',
            'layout'
        ));
    }

    /**
     * Approve or reject a student leave request.
     */
    public function update(Request $request, int $id, StudentLeaveService $service)
    {
        $validated = $request->validate([
            'status' => ['required', 'in:approved,rejected,Approved,Rejected'],
            'review_note' => ['nullable', 'string', 'max:500'],
        ]);

        $status = strtolower($validated['status']);
        $note = $validated['review_note'] ?? null;

        $service->reviewLeave($id, $status, $note, $request->user());

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Leave request has been '.ucfirst($status).'.',
                'status' => $status,
            ]);
        }

        return back()->with('success', 'Student leave request has been '.ucfirst($status).'.');
    }

    /**
     * Securely download or view the leave attachment.
     */
    public function attachment(Request $request, int $id)
    {
        $user = $request->user();
        $leave = StudentLeaveRequest::with('student')->findOrFail($id);

        if (! $leave->attachment_path) {
            abort(404, 'No attachment associated with this leave request.');
        }

        // Authorization check
        $isAdmin = $user->hasAnyRole(['Admin', 'Principal']);
        $isAssignedTeacher = false;
        if ($user->hasRole('Teacher') && $user->teacher) {
            $isAssignedTeacher = TeacherAssignment::where('teacher_id', $user->teacher->id)
                ->where('class_id', $leave->student?->class_id)
                ->exists();
        }

        $isOwnStudent = ($user->hasRole('Student') && $leave->student?->user_id === $user->id);
        $isOwnParent = false;
        if ($user->hasRole('Parent')) {
            $isOwnParent = $user->children()->where('students.id', $leave->student_id)->exists();
        }

        if (! $isAdmin && ! $isAssignedTeacher && ! $isOwnStudent && ! $isOwnParent) {
            abort(403, 'Unauthorized to view this attachment.');
        }

        if (! Storage::disk('local')->exists($leave->attachment_path)) {
            abort(404, 'Attachment file not found on disk.');
        }

        return Storage::disk('local')->response($leave->attachment_path);
    }
}
