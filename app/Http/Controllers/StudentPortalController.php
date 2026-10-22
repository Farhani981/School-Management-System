<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\Student;
use App\Models\StudentLeaveRequest;
use App\Services\PortalStudentResolver;
use App\Services\StudentAttendanceService;
use App\Services\StudentDashboardService;
use App\Services\StudentFeeService;
use App\Services\StudentLeaveService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StudentPortalController extends Controller
{
    /**
     * Dashboard API endpoint returning unified JSON metrics for the verified student.
     */
    public function dashboardApi(Request $request, StudentDashboardService $service)
    {
        $student = PortalStudentResolver::resolveStudent($request);
        if (! $student) {
            return response()->json([
                'error' => 'unlinked_student',
                'message' => 'No verified student profile is linked to this account.',
            ], 404);
        }

        return response()->json($service->getDashboardData($student));
    }

    /**
     * Portal Home / Dashboard overview.
     */
    public function home(Request $request, StudentFeeService $feeService)
    {
        $student = PortalStudentResolver::resolveStudent($request);
        if (! $student) {
            return view('portal.unlinked');
        }

        $feeSummary = $feeService->getSummary($student);

        return view('portal.home', compact('student', 'feeSummary'));
    }

    /**
     * Attendance Page UI (Task 3).
     */
    public function attendance(Request $request, StudentAttendanceService $service)
    {
        $student = PortalStudentResolver::resolveStudent($request);
        if (! $student) {
            return view('portal.unlinked');
        }

        $month = $request->query('month', now()->format('Y-m'));

        try {
            $monthData = $service->getMonthAttendance($student, $month);
        } catch (\Exception $e) {
            // Fallback to current month if requested month was invalid or in future
            $month = now()->format('Y-m');
            $monthData = $service->getMonthAttendance($student, $month);
        }

        $summaryData = $service->getAttendanceSummary($student);
        $leaveRequests = StudentLeaveRequest::where('student_id', $student->id)
            ->with('reviewer:id,name')
            ->latest()
            ->get();

        return view('portal.pages.attendance', compact('student', 'monthData', 'summaryData', 'leaveRequests', 'month'));
    }

    /**
     * Task 1: GET /student/attendance?month=YYYY-MM
     * Protected by resolveStudent(). student_id comes strictly from guard.
     */
    public function attendanceMonthApi(Request $request, StudentAttendanceService $service)
    {
        $student = PortalStudentResolver::resolveStudent($request);
        if (! $student) {
            return response()->json([
                'error' => 'unlinked_student',
                'message' => 'No verified student profile is linked to this account.',
            ], 404);
        }

        $month = $request->query('month');
        $data = $service->getMonthAttendance($student, $month);

        return response()->json($data);
    }

    /**
     * Task 1: GET /student/attendance/summary
     * Session/year-level summary: overall percentage, month-wise percentages, and total counts.
     */
    public function attendanceSummaryApi(Request $request, StudentAttendanceService $service)
    {
        $student = PortalStudentResolver::resolveStudent($request);
        if (! $student) {
            return response()->json([
                'error' => 'unlinked_student',
                'message' => 'No verified student profile is linked to this account.',
            ], 404);
        }

        $data = $service->getAttendanceSummary($student);

        return response()->json($data);
    }

    /**
     * Task 2: GET /student/leave
     * List own requests, newest first, with status.
     */
    public function leaveList(Request $request)
    {
        $student = PortalStudentResolver::resolveStudent($request);
        if (! $student) {
            return response()->json([
                'error' => 'unlinked_student',
                'message' => 'No verified student profile is linked to this account.',
            ], 404);
        }

        $leaves = StudentLeaveRequest::where('student_id', $student->id)
            ->with('reviewer:id,name')
            ->latest()
            ->get();

        return response()->json($leaves);
    }

    /**
     * Task 2: POST /student/leave
     * Create leave request. Both students and parents can submit.
     */
    public function leaveStore(Request $request, StudentLeaveService $service)
    {
        $student = PortalStudentResolver::resolveStudent($request);
        if (! $student) {
            return response()->json([
                'error' => 'unlinked_student',
                'message' => 'No verified student profile is linked to this account.',
            ], 404);
        }

        $validated = $request->validate([
            'from_date' => ['required', 'date_format:Y-m-d'],
            'to_date' => ['required', 'date_format:Y-m-d'],
            'reason' => ['required', 'string'],
            'attachment' => ['nullable', 'file', 'max:2048', 'mimes:pdf,jpg,jpeg,png'],
        ]);

        $leave = $service->applyLeave(
            $student,
            $validated,
            $request->file('attachment')
        );

        if ($request->expectsJson() || $request->is('student/*') || $request->is('portal/api/*')) {
            return response()->json([
                'message' => 'Leave request submitted successfully.',
                'leave' => $leave->load('reviewer'),
            ], 201);
        }

        return redirect()->route('portal.attendance')->with('success', 'Leave request submitted successfully.');
    }

    /**
     * Task 2: PATCH /student/leave/:id/cancel
     * Cancel leave request if pending and belongs to resolved student.
     */
    public function leaveCancel(Request $request, int $id, StudentLeaveService $service)
    {
        $student = PortalStudentResolver::resolveStudent($request);
        if (! $student) {
            return response()->json([
                'error' => 'unlinked_student',
                'message' => 'No verified student profile is linked to this account.',
            ], 404);
        }

        $leave = $service->cancelLeave($student, $id);

        if ($request->expectsJson() || $request->is('student/*') || $request->is('portal/api/*')) {
            return response()->json([
                'message' => 'Leave request cancelled successfully.',
                'leave' => $leave,
            ]);
        }

        return redirect()->route('portal.attendance')->with('success', 'Leave request cancelled.');
    }

    /**
     * Secure attachment download for resolved student/parent.
     */
    public function leaveAttachment(Request $request, int $id)
    {
        $student = PortalStudentResolver::resolveStudent($request);
        if (! $student) {
            abort(404);
        }

        $leave = StudentLeaveRequest::where('student_id', $student->id)->findOrFail($id);

        if (! $leave->attachment_path || ! Storage::disk('local')->exists($leave->attachment_path)) {
            abort(404, 'Attachment not found.');
        }

        return Storage::disk('local')->response($leave->attachment_path);
    }

    /**
     * My Performance placeholder.
     */
    public function performance(Request $request)
    {
        $student = PortalStudentResolver::resolveStudent($request);
        return view('portal.pages.performance', compact('student'));
    }

    /**
     * Online Quizzes placeholder.
     */
    public function quizzes(Request $request)
    {
        $student = PortalStudentResolver::resolveStudent($request);
        return view('portal.pages.quizzes', compact('student'));
    }

    /**
     * Results placeholder.
     */
    public function results(Request $request)
    {
        $student = PortalStudentResolver::resolveStudent($request);
        return view('portal.pages.results', compact('student'));
    }

    /**
     * Study Material placeholder.
     */
    public function studyMaterial(Request $request)
    {
        $student = PortalStudentResolver::resolveStudent($request);
        return view('portal.pages.study-material', compact('student'));
    }

    /**
     * Fees Page UI (Task 2).
     */
    public function fees(Request $request, StudentFeeService $service)
    {
        $student = PortalStudentResolver::resolveStudent($request);
        if (! $student) {
            return view('portal.unlinked');
        }

        $sessions = AcademicSession::orderByDesc('is_current')->get();
        $selectedSession = $request->query('session', $student->academic_session_id);

        $summary = $service->getSummary($student);
        $allChallans = $service->getChallans($student, 'all', $selectedSession);
        $unpaidChallans = $service->getChallans($student, 'unpaid', $selectedSession);
        $paidChallans = $service->getChallans($student, 'paid', $selectedSession);
        $payments = $service->getPayments($student);

        return view('portal.pages.fees', compact(
            'student',
            'summary',
            'sessions',
            'selectedSession',
            'allChallans',
            'unpaidChallans',
            'paidChallans',
            'payments'
        ));
    }

    /**
     * Task 1: GET /student/fees/summary
     * Return: totalPending, totalPaidThisSession, overdueAmount, nextDueDate, status ("paid" | "pending" | "overdue"), latest unpaid challan.
     */
    public function feeSummaryApi(Request $request, StudentFeeService $service)
    {
        $student = PortalStudentResolver::resolveStudent($request);
        if (! $student) {
            return response()->json([
                'error' => 'unlinked_student',
                'message' => 'No verified student profile is linked to this account.',
            ], 404);
        }

        return response()->json($service->getSummary($student));
    }

    /**
     * Task 1: GET /student/fees/challans?status=all|paid|unpaid|overdue&session=
     * Return a list of challans: id, challan no, month/period, fee heads (tuition, transport, etc. with amounts),
     * discount, late fine, arrears, total, paid amount, balance, due date, status, paid date.
     */
    public function feeChallansApi(Request $request, StudentFeeService $service)
    {
        $student = PortalStudentResolver::resolveStudent($request);
        if (! $student) {
            return response()->json([
                'error' => 'unlinked_student',
                'message' => 'No verified student profile is linked to this account.',
            ], 404);
        }

        $status = $request->query('status', 'all');
        $session = $request->query('session');

        return response()->json($service->getChallans($student, $status, $session));
    }

    /**
     * Task 1: GET /student/fees/challans/:id
     * Full challan details. Verify the challan belongs to the resolved student, otherwise return 404 (not 403, so existence is not revealed).
     */
    public function feeChallanDetailApi(Request $request, int $id, StudentFeeService $service)
    {
        $student = PortalStudentResolver::resolveStudent($request);
        if (! $student) {
            return response()->json([
                'error' => 'unlinked_student',
                'message' => 'No verified student profile is linked to this account.',
            ], 404);
        }

        $challan = $service->getChallanDetails($student, $id);
        if (! $challan) {
            return response()->json([
                'error' => 'not_found',
                'message' => 'Challan not found.',
            ], 404);
        }

        return response()->json($challan);
    }

    /**
     * Task 1: GET /student/fees/challans/:id/pdf
     * Generate and stream a printable challan PDF.
     */
    public function feeChallanPdf(Request $request, int $id, StudentFeeService $service)
    {
        $student = PortalStudentResolver::resolveStudent($request);
        if (! $student) {
            abort(404, 'Student profile not found.');
        }

        return $service->streamChallanPdf($student, $id);
    }

    /**
     * Task 1: GET /student/fees/payments
     * Payment history: receipt no, challan no, amount, payment date, method, received by.
     */
    public function feePaymentsApi(Request $request, StudentFeeService $service)
    {
        $student = PortalStudentResolver::resolveStudent($request);
        if (! $student) {
            return response()->json([
                'error' => 'unlinked_student',
                'message' => 'No verified student profile is linked to this account.',
            ], 404);
        }

        return response()->json($service->getPayments($student));
    }

    /**
     * Task 1: GET /student/fees/payments/:id/receipt
     * Generate a PDF receipt (ownership check required).
     */
    public function feePaymentReceiptPdf(Request $request, int $id, StudentFeeService $service)
    {
        $student = PortalStudentResolver::resolveStudent($request);
        if (! $student) {
            abort(404, 'Student profile not found.');
        }

        return $service->streamPaymentReceiptPdf($student, $id);
    }

    /**
     * Timetable placeholder.
     */
    public function timetable(Request $request)
    {
        $student = PortalStudentResolver::resolveStudent($request);
        return view('portal.pages.timetable', compact('student'));
    }

    /**
     * Library placeholder.
     */
    public function library(Request $request)
    {
        $student = PortalStudentResolver::resolveStudent($request);
        return view('portal.pages.library', compact('student'));
    }

    /**
     * Student / Parent Profile view.
     */
    public function profile(Request $request)
    {
        $student = PortalStudentResolver::resolveStudent($request);
        return view('portal.profile', compact('student'));
    }

    /**
     * Unlinked account fallback.
     */
    public function unlinked()
    {
        return view('portal.unlinked');
    }
}
