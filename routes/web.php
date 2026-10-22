<?php
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentAttendanceController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AccountController;

Route::middleware('guest')->group(function () {
    Route::get('login', [\App\Http\Controllers\Auth\LoginController::class, 'index'])->name('login');
    Route::post('login', [\App\Http\Controllers\Auth\LoginController::class, 'store'])->name('login.store');
    Route::get('register', [\App\Http\Controllers\Auth\LoginController::class, 'register'])->name('register');
    Route::post('register', [\App\Http\Controllers\Auth\LoginController::class, 'registerStore'])->middleware('throttle:5,1')->name('register.store');
    Route::get('forgot-password', [\App\Http\Controllers\Auth\PasswordController::class, 'request'])->name('password.request');
    Route::post('forgot-password', [\App\Http\Controllers\Auth\PasswordController::class, 'email'])->middleware('throttle:5,1')->name('password.email');
    Route::get('reset-password/{token}', [\App\Http\Controllers\Auth\PasswordController::class, 'reset'])->name('password.reset');
    Route::post('reset-password', [\App\Http\Controllers\Auth\PasswordController::class, 'update'])->middleware('throttle:5,1')->name('password.update');
    Route::post('attendance/login', [\App\Http\Controllers\Auth\LoginController::class, 'store'])->name('attendance.login.store');
});
Route::post('logout', [\App\Http\Controllers\Auth\LoginController::class, 'logout'])->middleware('auth')->name('logout');
Route::post('notifications/{notification}/read', [\App\Http\Controllers\NotificationController::class, 'read'])->middleware('auth')->name('notifications.read');
// Compatibility URLs for bookmarks and existing clients, using the same logout action.
Route::get('attendance/login', fn () => redirect()->route('login'))->name('attendance.login');
Route::post('attendance/logout', [\App\Http\Controllers\Auth\LoginController::class, 'logout'])->middleware('auth')->name('attendance.logout');

Route::get('/', function (\Illuminate\Http\Request $request) {
    $user = $request->user();
    if ($user->hasRole('Teacher')) {
        return redirect()->route('teacher.dashboard');
    }
    if ($user->hasAnyRole(['Admin', 'Principal'])) {
        return app(DashboardController::class)->index(app(\App\Services\DashboardStatsService::class));
    }
    if ($user->hasAnyRole(['Student','Parent'])) return redirect()->route('portal.home');
    abort(403, 'Your account does not have panel access.');
})->middleware('auth')->name('dashboard.home');

// Friendly panel entry URLs for users who open /admin or /teacher directly.
Route::middleware(['auth', 'role:Admin|Principal'])->group(function () {
    Route::get('/admin', fn () => redirect()->route('dashboard'))->name('admin.home');
    Route::get('/admin/dashboard', fn () => redirect()->route('dashboard'))->name('admin.dashboard');
});
Route::middleware(['auth', 'role:Teacher'])->group(function () {
    Route::get('/teacher', fn () => redirect()->route('teacher.dashboard'))->name('teacher.home');
});

Route::middleware('auth')->prefix('library')->name('library.')->group(function () {
    Route::get('/', fn () => redirect()->route('library.books.index'))
        ->middleware('can:viewAny,App\Models\LibraryBook')->name('index');
    Route::resource('books', \App\Http\Controllers\LibraryBookController::class);
    Route::resource('authors', \App\Http\Controllers\LibraryAuthorController::class);
    Route::resource('members', \App\Http\Controllers\LibraryMemberController::class)->except('destroy');
    Route::get('books/{book}/copies/create', [\App\Http\Controllers\LibraryBookCopyController::class, 'create'])->name('copies.create');
    Route::post('books/{book}/copies', [\App\Http\Controllers\LibraryBookCopyController::class, 'store'])->name('copies.store');
    Route::get('issue-return', [\App\Http\Controllers\LibraryBorrowingController::class, 'index'])->name('borrowing.index');
    Route::get('issue', [\App\Http\Controllers\LibraryBorrowingController::class, 'create'])->name('borrowing.create');
    Route::post('issue', [\App\Http\Controllers\LibraryBorrowingController::class, 'store'])->name('borrowing.store');
    Route::get('loans/{transaction}', [\App\Http\Controllers\LibraryBorrowingController::class, 'show'])->name('borrowing.show');
    Route::get('loans/{transaction}/return', [\App\Http\Controllers\LibraryBorrowingController::class, 'returnForm'])->name('borrowing.return');
    Route::post('loans/{transaction}/return', [\App\Http\Controllers\LibraryBorrowingController::class, 'receive'])->name('borrowing.receive');
});

Route::middleware(['auth', 'role:Admin|Principal'])->group(function () {
Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

Route::post('exams/{exam}/subjects', [\App\Http\Controllers\ExamController::class, 'subjects'])->name('exams.subjects.store');
Route::post('exams/{exam}/datesheet', [\App\Http\Controllers\ExamController::class, 'datesheet'])->name('exams.datesheet.save');
Route::get('exams/{exam}/results', [\App\Http\Controllers\ExamResultController::class, 'index'])->name('exams.results.index');
Route::get('exams/{exam}/results/{student}', [\App\Http\Controllers\ExamResultController::class, 'show'])->withTrashed()->name('exams.results.show');
Route::post('exams/{exam}/load-teachers', [\App\Http\Controllers\ExamController::class, 'loadTeachers'])->name('exams.teachers.load');
Route::get('exams/marks-review', [\App\Http\Controllers\ExamReviewController::class, 'index'])->name('exams.approvals');
Route::get('exams/papers/{paper}/review', [\App\Http\Controllers\ExamReviewController::class, 'show'])->name('exams.review');
Route::post('exams/papers/{paper}/review', [\App\Http\Controllers\ExamReviewController::class, 'action'])->name('exams.review.action');
Route::post('exams/papers/{paper}/deadline', [\App\Http\Controllers\ExamReviewController::class, 'deadline'])->name('exams.deadline');
Route::get('exams/{exam}/schedule', [\App\Http\Controllers\ExamScheduleController::class, 'index'])->name('exams.schedule.index');
Route::post('exams/{exam}/schedule', [\App\Http\Controllers\ExamScheduleController::class, 'store'])->name('exams.schedule.store');
Route::post('exams/{exam}/schedule/publish', [\App\Http\Controllers\ExamScheduleController::class, 'publish'])->name('exams.schedule.publish');
Route::post('exams/{exam}/cancel', [\App\Http\Controllers\ExamController::class, 'cancel'])->name('exams.cancel');
Route::resource('exams', \App\Http\Controllers\ExamController::class)->except('destroy');



Route::prefix('leaves')->name('leaves.')->group(function () {
    Route::get('types', [\App\Http\Controllers\LeaveSetupController::class, 'types'])->name('types.index');
    Route::post('types', [\App\Http\Controllers\LeaveSetupController::class, 'saveType'])->name('types.store');
    Route::put('types/{type}', [\App\Http\Controllers\LeaveSetupController::class, 'saveType'])->name('types.update');
    Route::get('balances', [\App\Http\Controllers\LeaveSetupController::class, 'balances'])->name('balances.index');
    Route::post('balances/initialize', [\App\Http\Controllers\LeaveSetupController::class, 'initialize'])->name('balances.initialize');
    Route::put('balances/{balance}', [\App\Http\Controllers\LeaveSetupController::class, 'adjust'])->name('balances.adjust');
});

Route::prefix('fees')->name('fees.')->middleware([\App\Http\Middleware\FeesAuthenticated::class, 'can:fees.manage'])->group(function () {
    Route::get('/', [\App\Http\Controllers\FeeSetupController::class, 'index'])->name('index');
    Route::prefix('setup')->name('setup.')->group(function () {
        Route::get('/', [\App\Http\Controllers\FeeSetupController::class, 'index'])->name('index');
        Route::resource('types', \App\Http\Controllers\FeeTypeController::class)->except('show')->parameters(['types' => 'type']);
        Route::get('structures', [\App\Http\Controllers\FeeStructureController::class, 'index'])->name('structures.index');
        Route::post('structures', [\App\Http\Controllers\FeeStructureController::class, 'store'])->name('structures.store');
        Route::post('structures/copy', [\App\Http\Controllers\FeeStructureController::class, 'copy'])->name('structures.copy');
        Route::resource('discounts', \App\Http\Controllers\DiscountController::class)->except('show');
        Route::get('students/search', [\App\Http\Controllers\FeeSetupController::class, 'students'])->name('students.search');
        Route::post('student-discounts', [\App\Http\Controllers\DiscountController::class, 'assign'])->name('student-discounts.store');
        Route::delete('student-discounts/{assignment}', [\App\Http\Controllers\DiscountController::class, 'unassign'])->name('student-discounts.destroy');
        Route::get('settings', [\App\Http\Controllers\FeeSettingsController::class, 'index'])->name('settings.index');
        Route::put('settings', [\App\Http\Controllers\FeeSettingsController::class, 'update'])->name('settings.update');
    });
    Route::post('students/{student}/overrides', [\App\Http\Controllers\StudentFeeOverrideController::class, 'store'])->name('overrides.store');
    Route::delete('students/{student}/overrides/{override}', [\App\Http\Controllers\StudentFeeOverrideController::class, 'destroy'])->name('overrides.destroy');
    Route::get('students/{student}/preview', [\App\Http\Controllers\FeeSetupController::class, 'preview'])->name('preview');
    Route::get('vouchers', [\App\Http\Controllers\FeeBillingController::class, 'index'])->name('vouchers.index');
    Route::post('vouchers', [\App\Http\Controllers\FeeBillingController::class, 'generate'])->name('vouchers.store');
    Route::get('vouchers/{voucher}', [\App\Http\Controllers\FeeBillingController::class, 'show'])->name('vouchers.show');
    Route::post('vouchers/{voucher}/cancel', [\App\Http\Controllers\FeeBillingController::class, 'cancel'])->name('vouchers.cancel');
    Route::get('collection', [\App\Http\Controllers\FeeBillingController::class, 'collection'])->name('collection.index');
    Route::post('vouchers/{voucher}/payments', [\App\Http\Controllers\FeeBillingController::class, 'collect'])->name('payments.store');
    Route::get('receipts/{payment}', [\App\Http\Controllers\FeeBillingController::class, 'receipt'])->name('receipts.show');
    Route::post('payments/{payment}/reverse', [\App\Http\Controllers\FeeBillingController::class, 'reverse'])->name('payments.reverse');
    Route::get('defaulters', [\App\Http\Controllers\FeeBillingController::class, 'defaulters'])->name('defaulters.index');
    Route::get('reports', [\App\Http\Controllers\FeeBillingController::class, 'reports'])->name('reports.index');
    Route::get('students/{student}/history', [\App\Http\Controllers\FeeBillingController::class, 'studentHistory'])->withTrashed()->name('students.history');
});

Route::prefix('attendance')->name('attendance.')->middleware(\App\Http\Middleware\AttendanceAuthenticated::class)->group(function () {
    Route::get('students',[\App\Http\Controllers\AttendanceController::class,'students'])->name('students.index');
    Route::post('students',[\App\Http\Controllers\AttendanceController::class,'saveStudents'])->name('students.store');
    Route::get('staff',[\App\Http\Controllers\AttendanceController::class,'staff'])->name('staff.index');
    Route::post('staff',[\App\Http\Controllers\AttendanceController::class,'saveStaff'])->name('staff.store');
    Route::get('reports',[\App\Http\Controllers\AttendanceReportController::class,'index'])->name('reports.index');
    Route::get('reports/export/{format}',[\App\Http\Controllers\AttendanceReportController::class,'export'])->name('reports.export');
    Route::resource('holidays',\App\Http\Controllers\HolidayController::class)->only(['index','store','update','destroy']);
});

Route::get('subjects/classes', [\App\Http\Controllers\ClassSubjectController::class, 'index'])->name('subjects.classes.index');
Route::post('subjects/classes', [\App\Http\Controllers\ClassSubjectController::class, 'store'])->name('subjects.classes.store');
Route::post('subjects/classes/copy', [\App\Http\Controllers\ClassSubjectController::class, 'copy'])->name('subjects.classes.copy');
Route::resource('subjects', \App\Http\Controllers\SubjectController::class)->except('show');

// Teachers: static paths precede resource parameters.
Route::get('teachers/import', [\App\Http\Controllers\TeacherTransferController::class, 'index'])->name('teachers.import');
Route::post('teachers/import', [\App\Http\Controllers\TeacherTransferController::class, 'store'])->name('teachers.import.store');
Route::get('teachers/import/template', [\App\Http\Controllers\TeacherTransferController::class, 'template'])->name('teachers.import.template');
Route::get('teachers/export/{format}', [\App\Http\Controllers\TeacherTransferController::class, 'export'])->name('teachers.export');
Route::post('teachers/setup', [\App\Http\Controllers\TeacherController::class, 'setup'])->name('teachers.setup');
Route::get('teachers/assignments', [\App\Http\Controllers\TeacherAssignmentController::class, 'index'])->name('teachers.assignments.index');
Route::post('teachers/assignments', [\App\Http\Controllers\TeacherAssignmentController::class, 'store'])->name('teachers.assignments.store');
Route::put('teachers/assignments/{assignment}', [\App\Http\Controllers\TeacherAssignmentController::class, 'update'])->name('teachers.assignments.update');
Route::delete('teachers/assignments/{assignment}', [\App\Http\Controllers\TeacherAssignmentController::class, 'destroy'])->name('teachers.assignments.destroy');
Route::post('teachers/assignments/{assignment}/class-teacher', [\App\Http\Controllers\TeacherAssignmentController::class, 'classTeacher'])->name('teachers.assignments.class-teacher');
Route::get('teachers/leaves', [\App\Http\Controllers\TeacherLeaveController::class, 'index'])->name('teachers.leaves.index');
Route::post('teachers/leaves', [\App\Http\Controllers\TeacherLeaveController::class, 'store'])->name('teachers.leaves.store');
Route::post('teachers/leaves/{leave}/review', [\App\Http\Controllers\TeacherLeaveController::class, 'review'])->name('teachers.leaves.review');
Route::get('teachers/payroll', [\App\Http\Controllers\TeacherPayrollController::class, 'index'])->name('teachers.payroll.index');
Route::post('teachers/payroll/generate', [\App\Http\Controllers\TeacherPayrollController::class, 'generate'])->name('teachers.payroll.generate');
Route::get('teachers/payroll/report', [\App\Http\Controllers\TeacherPayrollController::class, 'report'])->name('teachers.payroll.report');
Route::post('teachers/payroll/advances', [\App\Http\Controllers\TeacherPayrollController::class, 'advance'])->name('teachers.payroll.advances');
Route::post('teachers/payroll/attendance', [\App\Http\Controllers\TeacherPayrollController::class, 'attendance'])->name('teachers.payroll.attendance');
Route::post('teachers/payroll/{payroll}/paid', [\App\Http\Controllers\TeacherPayrollController::class, 'paid'])->name('teachers.payroll.paid');
Route::get('teachers/payroll/{payroll}/slip', [\App\Http\Controllers\TeacherPayrollController::class, 'slip'])->name('teachers.payroll.slip');
Route::get('teachers/{teacher}/documents/{document}', [\App\Http\Controllers\TeacherController::class, 'document'])->name('teachers.documents.download');
Route::delete('teachers/{teacher}/documents/{document}', [\App\Http\Controllers\TeacherController::class, 'deleteDocument'])->name('teachers.documents.destroy');
Route::resource('teachers', \App\Http\Controllers\TeacherController::class);

Route::get('/dashboard/calendar', [DashboardController::class, 'calendar'])->name('dashboard.calendar');
Route::resource('events', \App\Http\Controllers\SchoolEventController::class)->except('show')->middleware([\App\Http\Middleware\AttendanceAuthenticated::class, 'can:events.manage']);
Route::get('/admissions', [\App\Http\Controllers\AdmissionsController::class, 'index'])->name('admissions.index');
Route::post('/admissions/applications', [\App\Http\Controllers\AdmissionsController::class, 'store'])->name('admissions.applications.store');
Route::get('/admissions/new', [\App\Http\Controllers\AdmissionsController::class, 'create'])->name('admissions.create');
Route::post('/admissions/new', [StudentController::class, 'store'])->name('admissions.students.store');
Route::get('/admissions/register', [\App\Http\Controllers\AdmissionsController::class, 'register'])->name('admissions.register');
Route::get('/admissions/register/export/{format}', [\App\Http\Controllers\AdmissionsController::class, 'export'])->name('admissions.register.export');
Route::get('/admissions/applications/{application}/review', [\App\Http\Controllers\AdmissionsController::class, 'review'])->name('admissions.applications.review');
Route::post('/admissions/applications/{application}/approve', [\App\Http\Controllers\AdmissionsController::class, 'approve'])->name('admissions.applications.approve');
Route::post('/admissions/applications/{application}/reject', [\App\Http\Controllers\AdmissionsController::class, 'reject'])->name('admissions.applications.reject');
Route::get('/promotion', [\App\Http\Controllers\PromotionController::class, 'index'])->name('promotion.index');
Route::post('/promotion', [\App\Http\Controllers\PromotionController::class, 'store'])->name('promotion.store');
Route::get('/promotion/history', [\App\Http\Controllers\PromotionController::class, 'history'])->name('promotion.history');
// Student Management
Route::get('/students/export/{format}', [\App\Http\Controllers\StudentTransferController::class, 'export'])->name('students.export');
Route::get('/students/import', [\App\Http\Controllers\StudentTransferController::class, 'index'])->name('students.import');
Route::post('/students/import', [\App\Http\Controllers\StudentTransferController::class, 'import'])->name('students.import.store');
Route::get('/students/import/template', [\App\Http\Controllers\StudentTransferController::class, 'template'])->name('students.import.template');
Route::get('/students/import/errors', [\App\Http\Controllers\StudentTransferController::class, 'errors'])->name('students.import.errors');
Route::get('/students/promotion', [\App\Http\Controllers\StudentBulkController::class, 'promotion'])->name('students.promotion');
Route::post('/students/bulk', [\App\Http\Controllers\StudentBulkController::class, 'store'])->name('students.bulk');
Route::get('/students/{student}/id-card', \App\Http\Controllers\StudentIdCardController::class)->name('students.id-card');
Route::get('/students/{student}/certificates/{type}', [\App\Http\Controllers\StudentActionsController::class, 'certificate'])->name('students.certificate');
Route::post('/students/actions', [\App\Http\Controllers\StudentActionsController::class, 'bulk'])->name('students.actions');
Route::post('/students/{student}/status', [StudentController::class, 'changeStatus'])->name('students.status');
Route::post('/students/{student}/portal-login', [StudentController::class, 'managePortalLogin'])->name('students.portal-login');
Route::resource('students', StudentController::class)->except(['show']);

// Student Attendance
Route::get('/students/{student}/attendance', [StudentAttendanceController::class, 'index'])
    ->name('students.attendance');

Route::post('/students/{student}/attendance', [StudentAttendanceController::class, 'store'])
    ->name('students.attendance.store');
Route::get('/academic-setup', [\App\Http\Controllers\AcademicSetupController::class, 'index'])->name('academic-setup.index');
Route::post('/academic-setup/current', [\App\Http\Controllers\AcademicSetupController::class, 'current'])->middleware([\App\Http\Middleware\AttendanceAuthenticated::class, 'can:attendance.staff'])->name('academic-setup.current');
Route::post('/academic-setup', [\App\Http\Controllers\AcademicSetupController::class, 'store'])->name('academic-setup.store');
Route::get('/academic-setup/classes/{schoolClass}/edit', [\App\Http\Controllers\AcademicSetupController::class, 'editClass'])->name('academic-setup.classes.edit');
Route::put('/academic-setup/classes/{schoolClass}', [\App\Http\Controllers\AcademicSetupController::class, 'updateClass'])->name('academic-setup.classes.update');
Route::delete('/academic-setup/classes/{schoolClass}', [\App\Http\Controllers\AcademicSetupController::class, 'destroyClass'])->name('academic-setup.classes.destroy');
Route::post('/students/{student}/documents', [\App\Http\Controllers\StudentRecordsController::class, 'documents'])->name('students.documents.store');
Route::get('/students/{student}/documents/{document}', [\App\Http\Controllers\StudentRecordsController::class, 'download'])->name('students.documents.download');
Route::delete('/students/{student}/documents/{document}', [\App\Http\Controllers\StudentRecordsController::class, 'deleteDocument'])->name('students.documents.destroy');
Route::post('/students/{student}/promotion', [\App\Http\Controllers\StudentRecordsController::class, 'promote'])->name('students.promote');

// Timetable Management
Route::prefix('timetable')->name('timetable.')->group(function () {
    Route::get('/', [\App\Http\Controllers\TimetableController::class, 'index'])->name('index');
    Route::post('/entries', [\App\Http\Controllers\TimetableController::class, 'saveEntry'])->name('entries.store');
    Route::post('/daily-routine', [\App\Http\Controllers\TimetableController::class, 'repeatRoutine'])->name('routine.repeat');
    Route::get('/teacher-routine', [\App\Http\Controllers\TimetableController::class, 'teacherRoutine'])->name('teacher-routine');
    Route::post('/teacher-routine', [\App\Http\Controllers\TimetableController::class, 'saveTeacherRoutine'])->name('teacher-routine.store');
    Route::delete('/entries/{entry}', [\App\Http\Controllers\TimetableController::class, 'deleteEntry'])->name('entries.destroy');
    Route::post('/publish', [\App\Http\Controllers\TimetableController::class, 'publish'])->name('publish');
    Route::post('/unpublish', [\App\Http\Controllers\TimetableController::class, 'unpublish'])->name('unpublish');

    // Periods Slots
    Route::get('/periods', [\App\Http\Controllers\PeriodController::class, 'index'])->name('periods.index');
    Route::post('/periods', [\App\Http\Controllers\PeriodController::class, 'store'])->name('periods.store');
    Route::put('/periods/{period}', [\App\Http\Controllers\PeriodController::class, 'update'])->name('periods.update');
    Route::delete('/periods/{period}', [\App\Http\Controllers\PeriodController::class, 'destroy'])->name('periods.destroy');

    // Substitutions
    Route::get('/overrides', [\App\Http\Controllers\TimetableOverrideController::class, 'index'])->name('overrides.index');
    Route::post('/overrides', [\App\Http\Controllers\TimetableOverrideController::class, 'store'])->name('overrides.store');
    Route::delete('/overrides/{override}', [\App\Http\Controllers\TimetableOverrideController::class, 'destroy'])->name('overrides.destroy');
});
});

// Authenticated view route governed by StudentPolicy (Admin/Principal full access, Teacher class-assigned only)
Route::middleware(['auth'])->group(function () {
    // Old builder bookmarks opened under a teacher session go to the teacher timetable.
    // Builder authorization remains in the controller; writes stay Admin/Principal-only.
    Route::get('/timetable/builder', [\App\Http\Controllers\TimetableController::class, 'builder'])->name('timetable.builder');
    Route::get('/students/{student}', [StudentController::class, 'show'])->name('students.show');
});
// Portal access for Students and Parents
Route::prefix('portal')->name('portal.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/login', [\App\Http\Controllers\Portal\PortalAuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [\App\Http\Controllers\Portal\PortalAuthController::class, 'login'])->name('login.submit');
        Route::get('/forgot-password', [\App\Http\Controllers\Portal\PortalAuthController::class, 'showForgotPassword'])->name('password.forgot');
        Route::post('/forgot-password', [\App\Http\Controllers\Portal\PortalAuthController::class, 'sendResetInstructions'])->name('password.forgot.submit');
    });

    Route::middleware(['auth', 'portal.student'])->group(function () {
        Route::post('/logout', [\App\Http\Controllers\Portal\PortalAuthController::class, 'logout'])->name('logout');
        Route::post('/switch-child', [\App\Http\Controllers\Portal\PortalAuthController::class, 'switchChild'])->name('switch-child');
        Route::get('/change-password', [\App\Http\Controllers\Portal\PortalAuthController::class, 'showChangePassword'])->name('password.change');
        Route::post('/change-password', [\App\Http\Controllers\Portal\PortalAuthController::class, 'updatePassword'])->name('password.update');

        Route::get('/', [\App\Http\Controllers\StudentPortalController::class, 'home'])->name('home');
        Route::get('/dashboard', fn () => redirect()->route('portal.home'))->name('dashboard');
        Route::get('/attendance', [\App\Http\Controllers\StudentPortalController::class, 'attendance'])->name('attendance');
        Route::get('/performance', [\App\Http\Controllers\StudentPortalController::class, 'performance'])->name('performance');
        Route::get('/quizzes', [\App\Http\Controllers\StudentPortalController::class, 'quizzes'])->name('quizzes');
        Route::get('/results', [\App\Http\Controllers\StudentPortalController::class, 'results'])->name('results');
        Route::get('/study-material', [\App\Http\Controllers\StudentPortalController::class, 'studyMaterial'])->name('study-material');
        Route::get('/fees', [\App\Http\Controllers\StudentPortalController::class, 'fees'])->name('fees');
        Route::get('/timetable', [\App\Http\Controllers\StudentPortalController::class, 'timetable'])->name('timetable');
        Route::get('/library', [\App\Http\Controllers\StudentPortalController::class, 'library'])->name('library');
        Route::get('/profile', [\App\Http\Controllers\StudentPortalController::class, 'profile'])->name('profile');
        Route::get('/api/dashboard', [\App\Http\Controllers\StudentPortalController::class, 'dashboardApi'])->name('api.dashboard');
        Route::get('/api/attendance', [\App\Http\Controllers\StudentPortalController::class, 'attendanceMonthApi'])->name('api.attendance');
        Route::get('/api/attendance/summary', [\App\Http\Controllers\StudentPortalController::class, 'attendanceSummaryApi'])->name('api.attendance.summary');
        Route::get('/api/leave', [\App\Http\Controllers\StudentPortalController::class, 'leaveList'])->name('api.leave.index');
        Route::post('/api/leave', [\App\Http\Controllers\StudentPortalController::class, 'leaveStore'])->name('api.leave.store');
        Route::patch('/api/leave/{id}/cancel', [\App\Http\Controllers\StudentPortalController::class, 'leaveCancel'])->name('api.leave.cancel');
        Route::get('/api/leave/{id}/attachment', [\App\Http\Controllers\StudentPortalController::class, 'leaveAttachment'])->name('api.leave.attachment');
        Route::get('/api/fees/summary', [\App\Http\Controllers\StudentPortalController::class, 'feeSummaryApi'])->name('api.fees.summary');
        Route::get('/api/fees/challans', [\App\Http\Controllers\StudentPortalController::class, 'feeChallansApi'])->name('api.fees.challans');
        Route::get('/api/fees/challans/{id}', [\App\Http\Controllers\StudentPortalController::class, 'feeChallanDetailApi'])->name('api.fees.challans.show');
        Route::get('/api/fees/challans/{id}/pdf', [\App\Http\Controllers\StudentPortalController::class, 'feeChallanPdf'])->name('api.fees.challans.pdf');
        Route::get('/api/fees/payments', [\App\Http\Controllers\StudentPortalController::class, 'feePaymentsApi'])->name('api.fees.payments');
        Route::get('/api/fees/payments/{id}/receipt', [\App\Http\Controllers\StudentPortalController::class, 'feePaymentReceiptPdf'])->name('api.fees.payments.receipt');
        Route::get('/unlinked', [\App\Http\Controllers\StudentPortalController::class, 'unlinked'])->name('unlinked');
    });
});

// Student Portal Endpoints (/student/*)
Route::middleware(['auth', 'portal.student'])->prefix('student')->name('student.')->group(function () {
    Route::get('/', fn () => redirect()->route('portal.home'))->name('home');
    Route::get('/dashboard', [\App\Http\Controllers\StudentPortalController::class, 'dashboardApi'])->name('dashboard');
    Route::get('/attendance', [\App\Http\Controllers\StudentPortalController::class, 'attendanceMonthApi'])->name('attendance');
    Route::get('/attendance/summary', [\App\Http\Controllers\StudentPortalController::class, 'attendanceSummaryApi'])->name('attendance.summary');
    Route::get('/leave', [\App\Http\Controllers\StudentPortalController::class, 'leaveList'])->name('leave.index');
    Route::post('/leave', [\App\Http\Controllers\StudentPortalController::class, 'leaveStore'])->name('leave.store');
    Route::patch('/leave/{id}/cancel', [\App\Http\Controllers\StudentPortalController::class, 'leaveCancel'])->name('leave.cancel');
    Route::get('/leave/{id}/attachment', [\App\Http\Controllers\StudentPortalController::class, 'leaveAttachment'])->name('leave.attachment');
    
    // Fees APIs (Task 1)
    Route::get('/fees/summary', [\App\Http\Controllers\StudentPortalController::class, 'feeSummaryApi'])->name('fees.summary');
    Route::get('/fees/challans', [\App\Http\Controllers\StudentPortalController::class, 'feeChallansApi'])->name('fees.challans');
    Route::get('/fees/challans/{id}', [\App\Http\Controllers\StudentPortalController::class, 'feeChallanDetailApi'])->name('fees.challans.show');
    Route::get('/fees/challans/{id}/pdf', [\App\Http\Controllers\StudentPortalController::class, 'feeChallanPdf'])->name('fees.challans.pdf');
    Route::get('/fees/payments', [\App\Http\Controllers\StudentPortalController::class, 'feePaymentsApi'])->name('fees.payments');
    Route::get('/fees/payments/{id}/receipt', [\App\Http\Controllers\StudentPortalController::class, 'feePaymentReceiptPdf'])->name('fees.payments.receipt');

    Route::get('/performance', fn () => redirect()->route('portal.performance'))->name('performance');
    Route::get('/quizzes', fn () => redirect()->route('portal.quizzes'))->name('quizzes');
    Route::get('/results', fn () => redirect()->route('portal.results'))->name('results');
    Route::get('/study-material', fn () => redirect()->route('portal.study-material'))->name('study-material');
    Route::get('/fees', fn () => redirect()->route('portal.fees'))->name('fees');
    Route::get('/timetable', fn () => redirect()->route('portal.timetable'))->name('timetable');
    Route::get('/library', fn () => redirect()->route('portal.library'))->name('library');
    Route::get('/profile', fn () => redirect()->route('portal.profile'))->name('profile');
});

// Admin Student Leave Requests
Route::middleware(['auth', 'role:Admin|Principal'])->prefix('admin/student-leaves')->name('admin.student-leaves.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Admin\StudentLeaveApprovalController::class, 'index'])->name('index');
    Route::post('/{id}/review', [\App\Http\Controllers\Admin\StudentLeaveApprovalController::class, 'update'])->name('update');
    Route::get('/{id}/attachment', [\App\Http\Controllers\Admin\StudentLeaveApprovalController::class, 'attachment'])->name('attachment');
});
///Accounts Management
Route::middleware(['auth', 'role:Admin|Principal'])->prefix('accounts')->name('accounts.')->group(function () {
    $overview = \App\Http\Controllers\AccountOverviewController::class;
    Route::get('/', [$overview, 'dashboard'])->name('dashboard');
    Route::get('/income', [$overview, 'income'])->name('income.index');
    Route::post('/income', [$overview, 'storeIncome'])->name('income.store');
    Route::get('/transactions', fn () => redirect()->route('accounts.reports.index'))->name('transactions.index');
    Route::get('/reports', [$overview, 'transactions'])->name('reports.index');
    Route::get('/staff-salary', [$overview, 'salaries'])->name('salaries.index');
    Route::post('/staff-salary/generate', [$overview, 'generate'])->name('salaries.generate');
    Route::post('/staff-salary/{payroll}/paid', [$overview, 'pay'])->name('salaries.paid');
    Route::get('/attachments/{type}/{id}', [$overview, 'attachment'])->name('attachments.show');
    Route::get('/expenses', [AccountController::class, 'index'])
        ->name('expenses.index');

    Route::get('/expenses/create', [AccountController::class, 'create'])
        ->name('expenses.create');

    Route::post('/expenses', [AccountController::class, 'store'])
        ->name('expenses.store');
});
