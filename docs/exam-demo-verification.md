# Exam verification — 7 October 2026

User requested a saved sample exam, datesheet and results to check the module.

## Saved demo

- Exam 2: **DEMO CHECK - October 2026 Exam**, current session 2026-2027.
- Dedicated DEMO CHECK class, section, examiner account and three fictitious students.
- Mathematics: 12 October 2026, 09:00–11:00; English: 13 October 2026, 09:00–11:00.
- Datesheet saved and published through ExamSetup/ExamDatesheet services. Only the new demo examiner account received the database publication notification.
- Two linked assessments and six result fixtures: Demo Pass scored 82/100 and 88/100; Demo Fail scored 28/100 and 34/100; Demo Absent has no marks for either subject.
- Existing school records, settings, assignments and passwords were not edited. The new demo examiner has a randomly generated password, not a public demo credential.

## Verified

The local application's HTTP kernel returned 200 and expected demo content for admin exam detail, admin datesheet, admin quiz reports, admin results, teacher exam list, teacher datesheet and teacher results. The report included Pass, Fail and Absent. Percentage, pass/fail and absent grade calculations matched expectations. Datesheet checklist passed; PDF generated successfully.

ExamSetupTest, ExamScheduleTest and TeacherQuizTest: **31 tests passed, 288 assertions**. These include schedule conflicts, holidays, publication, role/assignment scope, PDF, structure locks and saved report rendering.

## Incomplete workflow

This is not a successful result upload through the UI. Linked-exam marks entry and CSV upload are not implemented; QuizPolicy deliberately rejects enterMarks for assessments with exam_subject_id. The saved demo's teacher marks URL returned **403**, confirming this limitation. Sample results were inserted directly as clearly labeled demo fixtures for report verification.

Reports currently show individual student/assessment records. This check does not establish a combined exam grand total, exam-wide grade/rank, or a submit/approve/publish result workflow. The exam remains **scheduled** even though sample fixture results exist.

## Review

Open Examinations → Exam and select DEMO CHECK - October 2026 Exam. In Exam Schedule select this exam. In Quiz Reports or Exam Result search DEMO CHECK or select a demo assessment.

Artifacts: storage/app/demo-exam-datesheet.pdf, storage/app/demo-exam-results.csv and storage/app/demo-exam-check.json. Saved HTTP HTML responses are storage/app/demo-check-*.html. No interactive browser inspection was performed.

Local scripts: create-exam-check.php refuses to duplicate/overwrite the demo; verify-exam-check.php checks the saved records and regenerates artifacts. These files are under storage/app and have no public route.
