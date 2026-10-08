<?php

namespace App\Providers;

use App\Models\AcademicSession;
use App\Models\Activity;
use App\Models\AdmissionApplication;
use App\Models\Discount;
use App\Models\Holiday;
use App\Models\Promotion;
use App\Models\SchoolClass;
use App\Models\SchoolEvent;
use App\Models\SchoolSection;
use App\Models\StaffAttendance;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\Teacher;
use App\Observers\DashboardObserver;
use App\Observers\FeeSetupObserver;
use App\Policies\AttendancePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(\App\Models\Exam::class, \App\Policies\ExamPolicy::class);
        Gate::policy(\App\Models\TeacherAssignment::class, \App\Policies\TeacherAssignmentPolicy::class);
        Gate::policy(\App\Models\Quiz::class, \App\Policies\QuizPolicy::class);
        Gate::policy(\App\Models\StudentNote::class, \App\Policies\StudentNotePolicy::class);
        Gate::policy(SchoolClass::class, AttendancePolicy::class);
        Gate::policy(Student::class, \App\Policies\StudentPolicy::class);
        Gate::define('attendance.staff', fn ($user) => AttendancePolicy::admin($user));
        Gate::define('events.manage', fn ($user) => AttendancePolicy::admin($user));
        Gate::define('fees.manage', fn ($user) => AttendancePolicy::admin($user));
        Gate::define('fees.view', fn ($user) => ! $user->hasRole('Teacher') && ($user->hasAnyRole(['Admin', 'Principal']) || $user->can('fees.manage')));
        Discount::observe(FeeSetupObserver::class);
        \App\Models\Exam::observe(DashboardObserver::class);
        foreach ([Student::class, Teacher::class, SchoolClass::class, SchoolSection::class, AcademicSession::class, StudentAttendance::class, StaffAttendance::class, Holiday::class, SchoolEvent::class, Activity::class, Promotion::class, AdmissionApplication::class, \App\Models\Quiz::class, \App\Models\QuizResult::class] as $model) {
            $model::observe(DashboardObserver::class);
        }

        \Illuminate\Support\Facades\View::composer('teacher.partials.sidebar', function ($view) {
            $user = auth()->user();
            if ($user && $user->teacher) {
                $view->with('pendingQuizMarks', app(\App\Services\QuizService::class)->getPendingMarksCount($user->teacher));
            }
        });
    }
}
