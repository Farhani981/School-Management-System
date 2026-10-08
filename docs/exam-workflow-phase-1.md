# Phase 1: examination workflow database foundation

Completed on 7 October 2026 after the full A–K project audit. This phase supplies relationships and metadata; it does not implement the marks-entry, approval or result-publication screens.

## 1. What are we building?

A reliable link between a scheduled examination paper and its existing quiz assessment, a place to save the original student roster, submission/reviewer metadata, an independent final-publication record and exam-level audit relationships.

## 2. Why is it needed?

Later phases must identify exactly which paper a teacher is marking, preserve student names/roll numbers when students move classes or are deactivated, record who submitted/reviewed marks and distinguish approved marks from a published final result. Marks remain in quiz_results.

## 3. Existing files changed

- app/Models/Exam.php: description/publication casts and schedule/log/publication-actor relationships.
- app/Models/ExamSchedule.php: deadline/submission/reviewer/roster casts and assessment/actor/log relationships.
- app/Models/Quiz.php: explicit schedule link and roster-entry relationship.
- app/Services/ExamDatesheet.php: new schedule audit records also link directly to the parent exam.
- tests/Feature/ExamScheduleTest.php: foundation relationships, original student identity retention and migration backfill tests.

## 4. New files

- database/migrations/2026_10_07_000100_add_exam_workflow_foundation.php.
- app/Models/ExamRosterEntry.php.
- app/Models/ExamLog.php.
- docs/exam-system-audit-and-plan.md and this phase explanation.
- storage/app/audit-exam-system.php and the pre-migration schema snapshot storage/app/exam-system-audit.json.

## 5. Tables involved

| Table | Change |
| --- | --- |
| exams | Description; final result publication actor/time/version and settings snapshot |
| exam_schedules | Marks deadline, late-submission permission, submitter/reviewer metadata and roster-freeze timestamp |
| quizzes | Unique nullable exam_schedule_id; existing exact subject/section pairs backfilled |
| exam_roster_entries | New quiz/student membership with original admission number, name and roll number; no marks |
| exam_logs | Optional schedule, exam relationship; existing schedule log rows linked to their exam |

Existing marks_status/return_reason, quiz_results, quiz_result_audits, shared grades and notifications are reused. Student enrollment dimensions remain on the assessment; the new roster identifies which students were members before entry. Frozen roster capture and enforcement will be introduced in the marks-entry phase, not automatically inferred from today's enrollment.

## 6–7. What do Admin and Teacher do?

Their current screens remain available. There is no new button in this database-only phase. Admin still configures/publishes datesheets. Teacher still views assigned schedules; linked exam marks entry remains unavailable pending its dedicated workflow.

## 8. What happens after clicking a button?

Existing buttons retain their current behavior. The migration backfilled the two previously created demo assessment/schedule links and four existing schedule log/exam links. It did not approve marks, freeze a roster, publish results or change school settings.

## 9. How does data move?

Exam → configured subject → scheduled paper → linked Quiz → existing QuizResult marks. Quiz → ExamRosterEntry captures original roster membership in a later phase. Schedule stores draft/submitted/returned/approved metadata. Exam stores independent final publication metadata. ExamLog stores actor, action, time and old/new values for later lifecycle actions.

## 10. How can I test it?

Run `php artisan test --filter='ExamScheduleTest|ExamSetupTest|TeacherQuizTest|StudentProgressTest'`. Result: **42 tests passed, 359 assertions**. Tests include schedule-assessment relationships, a historical name/roll snapshot surviving student rename/soft deletion, exam-level audit relationships, backfill preserving workflow statuses and existing exam/quiz/progress behavior.

The additive migration was applied successfully to the workspace MySQL database using its exact migration path. The saved demo's seven exam/schedule/report endpoints still returned expected 200 responses; the legacy linked-exam marks route still returned the expected 403. There were no asset changes, so no frontend rebuild was needed.

## Next logical phase

Phase 2 completes Admin Exam Management: description, expanded exam types, clear lifecycle/publication labeling, setup completeness and corresponding validation/audit behavior. Phases 3–4 extend schedule/teacher metadata; phase 5 supplies dedicated exam marks entry and publication gating. The remaining phases and required routes/controllers/views are specified in the full audit. This phase must not be described as a complete examination system.
