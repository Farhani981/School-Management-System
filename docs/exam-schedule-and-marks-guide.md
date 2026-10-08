# From exam setup to teacher marks

The earlier screenshots showed an unfinished draft, missing teacher assignments and a teacher schedule page without published papers. Formal marks entry had not yet been implemented. This change connects datesheet scheduling/publication, teacher marks, submission, correction and admin approval.

## Admin: start with one class/section if learning the system

1. Open Examinations -> Exams -> Open Exam. Select only the classes/sections and subjects actually taking the exam.
2. Total and passing marks on this screen are subject limits, not a student's obtained marks.
3. If a subject says No teacher assigned, click Add assignment. The corresponding class, section, subject and session are preselected. Choose the responsible teacher and add the assignment. Return to Exam Setup, confirm that teacher and save the class subjects.
4. Once setup is complete, click Next: Set Paper Dates.
5. Enter each paper's date, start/end times, room, invigilator and marks submission deadline. Dates must fall within the examination dates; deadline must follow paper end. If the exam date range is wrong, edit exam details before creating schedule records.
6. Save Datesheet, resolve the checklist, then Publish Datesheet. A saved draft datesheet does not appear for teachers.

## Teacher: where to enter marks

Open My Exams -> Enter Marks on the assigned paper. The teacher dashboard also shows the same button. My Exam Schedule shows the published dates and timings.

Enter obtained marks and choose Present/Absent. Save Draft allows incomplete rows. Zero is a real entered mark; Absent has no mark. Submit Marks requires every frozen roster member to have valid marks or Absent, and locks normal editing. Teachers receive only their exact assigned published papers; cancelled or unassigned examinations stay hidden.

## Admin: review and corrections

Open Examinations -> Marks Review -> Review Marks. Approve & Lock protects the paper; Return for Correction requires a reason. The teacher opens the returned paper, corrects marks and resubmits. If the deadline has passed, use Extend marks submission deadline on the review page and provide a reason.

Approval does not publish a final result. Combined result calculation/publication and report cards remain a subsequent stage.

## Implementation

- app/Services/ExamDatesheet.php: validated schedule save/publication, class/section-room-invigilator overlap prevention, date/time validation and Sunday/holiday exceptions with reasons.
- app/Services/ExamWorkflow.php: dedicated formal marks authorization, frozen roster, partial draft save, complete submission, locks, review/return/approval and audit history.
- app/Http/Controllers/ExamScheduleController.php, ExamReviewController.php, Teacher/ExamMarksController.php: real request handlers.
- app/Notifications/ExamWorkflowUpdated.php: school database notifications.
- resources/views/exams/schedule.blade.php, approvals.blade.php, review.blade.php and teacher/exams/marks.blade.php: working screens.
- Existing routes, dashboards, teacher assignment links, exam setup copy and school theme are updated. Quizzes retain their independent workflow. The existing linked assessment storage is reused without duplicating marks into another table.

No new database migration is required. No existing school exam, enrolment or teacher assignment was automatically changed. The visual preview uses rollback-only transactions.

## Checks

    php artisan test
    npm.cmd run build
    php artisan view:clear

Full suite: 143 tests passed, 1365 assertions before final link/audit improvements. Focused workflow checks cover draft -> published datesheet -> marks draft -> submitted -> returned -> resubmitted -> approved, absent/zero handling, incomplete submissions, foreign teacher denial, mark limits, deadlines, dates/holiday/time conflicts and notifications. Browser checks cover datesheet, teacher papers, marks entry and review at desktop/mobile widths with no page overflow or JavaScript exceptions; Present/Absent input behavior is verified.

Evidence: storage/app/exam-connected-workflow-tests.txt, exam-connected-ui-check.json and exam-workflow-*.png.

Final verification after assignment links and paper-linked audit updates: 143 tests passed (1365 assertions). Frontend build and all 8 desktop/mobile browser checks passed.
