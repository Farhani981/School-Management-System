# Examination rebuild ? Phase 1 audit

Audited 7 October 2026 against the user-supplied Professional Examination Management Module specification. This is an audit, not a completed examination implementation.

## Working

- Academic sessions, students, school classes, sections, subjects and class-subject configurations already exist.
- TeacherAssignment stores teacher/class/section/subject/session relationships. Existing policies enforce teacher ownership.
- Independent teacher quizzes support creation, marks, import/export and grading. QuizService and StudentProgressService exclude archived exam-linked assessments.
- GradeScale::forPercentage is an existing centralized lookup; reuse it instead of duplicating grading calculations.
- Authentication and Admin/Principal/Teacher role middleware are available. Laravel database notifications and ExamLog storage exist.
- Current targeted verification: 27 tests passed, 222 assertions, across TeacherQuizTest, SubjectsModuleTest and TeacherPanelTest.

## Incomplete / retained foundation

- Exam, ExamSubject, ExamSchedule, ExamRosterEntry, ExamSetting and ExamLog models remain, with historical migrations applied.
- Local database contains 2 exams, 9 subject configurations, 2 scheduled papers, 6 roster entries and 22 exam logs. These records are archived, not evidence of a working current workflow.
- Exam classes are configured through exam_classes, but there is no explicit participating-section selection table. Schedules currently identify sections. Phase 2 needs explicit section selection with valid class/session enrolment and assignment checks.
- ExamSubject defines marks at class/subject level; responsible teacher and section currently live on ExamSchedule. Use one validated paper per exam/class/section/subject, drawing teacher eligibility from TeacherAssignment.
- Approved/submitted/returned metadata and result snapshots exist in the schema but have no current application actions.

## Missing current application functionality

Exam creation/edit/cancellation, datesheet editing/conflict validation/publication, teacher examination lists, formal marks entry/draft/submission/correction, admin review/approval/unlock, combined results/ranking, publication checklist, report cards/PDF, grades management, examination reports, workflow notifications and deadline extensions/reminders.

These controllers/services/views/routes were removed at the user's preceding request. Rebuild them in the requested phases rather than presenting the archived schema as an active module.

## Broken or mismatched for the requested specification

- Exam::STATUSES currently has only draft/scheduled/marks_entry/published, and labels published as Completed. It cannot represent the requested schedule publication, ongoing, completed, result pending, result published and cancellation clearly. Existing stored statuses must be translated safely.
- The legacy exam marks route through QuizPolicy deliberately denies exam-linked assessments. Do not remove this protection. Implement a dedicated formal-exam policy checking role, active teacher, assigned teacher, class, section, subject, session and publication/cancellation state; validate marks status and deadline on writes.
- Existing grades use F below 50 and no E range. The supplied document gives E at 40?49 as an example, not permission to silently overwrite the shared quiz grade scale. Provide configurable grading with complete decimal-range coverage.
- Shared quiz storage contains archived exam assessments. Keep formal exam routes and commands separate, retaining archived quiz restrictions. Decide compatible storage in Phase 2 before adding marks tables; do not duplicate the same marks in two stores.

## Roles and data flow

Admin: create exam, select session/classes/sections, configure subjects and marks, confirm teacher assignments, publish schedule, review submissions, approve or return, publish results.
Teacher: see assigned published papers, enter Present/Absent and marks, save incomplete draft, submit complete paper, correct returned marks and resubmit. Cannot configure or publish official exams.
System: validate ownership and state, freeze examination roster, enforce conflicts/deadlines, calculate grades/pass rules/tied ranks, publish immutable snapshots, notify and log actions.

Relationships: Exam -> AcademicSession; Exam -> classes/selected sections -> ExamSubject -> scheduled section paper -> assigned teacher -> frozen student roster -> formal marks -> submission review -> published result snapshot. Reuse existing Student, TeacherAssignment, SchoolClass, SchoolSection, Subject and GradeScale models.

## Phase 2 scope and likely files

- Modify app/Models/Exam.php for clear statuses and safe legacy interpretation.
- Add a new additive migration for explicit exam participation by class/section and any required compatibility updates. Do not edit applied migrations or clear records.
- Add app/Policies/ExamPolicy.php, app/Services/ExamSetup.php and app/Http/Controllers/ExamController.php.
- Add resources/views/exams/index.blade.php and form/show views, resources/css/exams.css, exam setup routes in routes/web.php and navigation in resources/views/components/sidebar.blade.php.
- Add tests/Feature/ExamSetupTest.php; update the module-removal assertion when routes are intentionally reinstated.
- Teacher exam access is Phase 4; do not display broken links to unimplemented actions.

## Commands and evidence

Run from C:\school management system:

    php artisan migrate:status
    php storage/app/exam-rebuild-audit.php
    php artisan test --filter="TeacherQuizTest|SubjectsModuleTest|TeacherPanelTest"

Expected current result: all historical migrations Ran; a schema/count snapshot without student personal details; 27 passing tests. The read-only audit script writes its report to storage/app/exam-rebuild-audit.json and does not mutate school records.

No application workflow code or database records were changed in Phase 1. There is no implementation code to paste in this audit phase. This report, the read-only audit script and its JSON evidence are the current deliverables.

The supplied requirement 49 asks to wait until each phase is understood before advancing. Phase 2 remains pending that understanding; the final module is not complete.
