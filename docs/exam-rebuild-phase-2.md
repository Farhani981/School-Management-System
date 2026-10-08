# Phase 2 ? simple examination setup

This phase builds the real Admin/Principal setup flow. Scheduling, teacher exam marks, approval and result publication are subsequent phases, not completed features.

## What to use

Open Examinations ? Exams ? Create Examination.

1. Enter exam name/type, current academic session and start/end dates. Description is optional.
2. Tick participating sections under each class. Click Save & Choose Subjects.
3. Open a class tab, tick its subjects, set total/passing marks, and confirm the responsible teacher for each section. Click Save [Class] Subjects.
4. Repeat for the other classes. The checklist changes to Exam setup is ready when all participating classes/sections have subjects and valid teachers.

If a teacher is missing, use Add assignment to open Teachers ? Assignments. Return to the exam and reload its setup. Only existing active teacher assignments for the exact class/section/subject/session can be selected.

The exam remains a draft during setup. Edit Details changes participation and dates before scheduling starts. Cancellation requires a reason and preserves the exam records. There is no delete button.

## Why two steps

Teachers need one clear paper assignment. The administrator first establishes which sections are taking part, then chooses subjects and responsibility. The same school colors, typography, form controls, buttons and sidebar are reused. Draft setup is not shown in the teacher quiz menu.

## Teacher behavior in this phase

Teacher quizzes continue to work independently. Teachers cannot create, edit or cancel official examinations. Formal exam schedules and marks access arrive in their own phases. No links to those unfinished screens are displayed.

## Database relationships

- Exam belongs to AcademicSession and participating classes through the existing exam_classes table.
- New exam_participations records a unique exam/class/section combination.
- Existing exam_subjects stores one subject/marks configuration per exam/class.
- New exam_subject_assignments records one responsible teacher for each exam subject/section. The teacher must already have the matching TeacherAssignment.
- Existing exam_logs records creation, edits, subject changes and cancellation with the actor, before/after values and reason.

Existing scheduled records are backfilled into the new participation/assignment tables. Historical exam statuses and published results are preserved; the UI interprets legacy statuses using explicit schedule/result publication timestamps.

## Files and complete implementation

Complete code is already saved in these workspace files; no copy/paste is necessary:

- app/Http/Controllers/ExamController.php ? list/search/create/edit/details/subject configuration/cancellation requests.
- app/Services/ExamSetup.php ? transactional validation, draft protection, assignment checks, readiness checklist and audit writes.
- app/Policies/ExamPolicy.php ? Admin/Principal setup permission.
- app/Models/Exam.php and app/Models/ExamSubject.php ? relationships and lifecycle labels.
- app/Models/ExamParticipation.php and app/Models/ExamSubjectAssignment.php ? new participation/responsibility models.
- database/migrations/2026_10_07_000300_add_exam_participation_and_assignments.php ? additive tables and historical backfill.
- routes/web.php and app/Providers/AppServiceProvider.php ? routes and policy registration.
- resources/views/exams/index.blade.php, form.blade.php and show.blade.php ? the three real screens.
- resources/views/components/sidebar.blade.php ? Examinations ? Exams navigation.
- resources/css/exams.css and resources/css/app.css ? scoped styling using existing colors.
- resources/js/app.js ? only included subjects require inputs; unchecked subjects stay disabled.
- tests/Feature/ExamSetupTest.php and tests/Feature/TeacherQuizTest.php ? workflow validation and quiz separation.

## Terminal commands

Use the terminal in C:\school management system. The new migration has already been applied locally. On another installation, run:

    php artisan migrate --path=database/migrations/2026_10_07_000300_add_exam_participation_and_assignments.php
    npm.cmd run build
    php artisan view:clear

To verify:

    php artisan test --filter="ExamSetupTest|TeacherQuizTest"
    php artisan test

Expected: passing tests; an exam is saved as Draft; the selected sections and configured subjects/marks/teachers reappear after reopening it. Incorrect date ranges, archived sessions, passing marks above total and unassigned/inactive teachers are rejected without partially overwriting saved records. Scheduled/published/cancelled/past-session setup is protected.

## Browser checks

List, creation, historical draft and configured draft screens were checked at 1366px and 390px. The configured draft preview is rendered inside a rollback transaction and leaves no new school record. Subject checkboxes enable/disable their required inputs correctly. Artifacts are in storage/app/exam-setup-ui-check.json and exam-setup-*.png; the regression output is storage/app/exam-phase-2-tests.txt.

Current phase only: setup. Next phase: datesheet, rooms, invigilators, deadlines, conflict validation and schedule publication. The supplied requirement 49 asks to confirm understanding of this phase before continuing.

Final verification: 139 tests passed (1299 assertions). Frontend production build passed. Eight desktop/mobile screen checks passed with no page overflow or JavaScript exceptions; configured-subject input toggling passed at both widths.
