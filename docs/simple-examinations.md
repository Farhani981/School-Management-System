# Examination workflow

1. Admin creates an exam with its date range and participating classes/sections.
2. Open Create Datesheet. Each class has a tab. Include the subjects being examined, set theory/practical maximums, passing marks, paper date/time and select a teacher for each section.
3. Save Draft keeps the datesheet editable. Send to Teachers saves and publishes in one transaction and notifies the selected teacher accounts. Teachers must have an active teacher record and teacher-role account.
4. Teachers open My Examinations, enter marks and optional remarks, or download and upload the CSV template. Submit sends marks to the administrator. Draft marks do not appear as completed student results. Submission locks editing until admin returns the paper for correction.
5. Admin opens Student Results for that exam. Submitted or approved subject marks combine into each student's report card, with theory/practical marks, total, percentage, configured grade, class/section rank, remarks and signature spaces. Print and Download PDF are available. Incomplete reports show Pending and have no final grade/rank.

Exam responsibility lives in `exam_subject_assignments` and `exam_schedules`. Selecting an exam teacher never creates or changes `teacher_assignments`; ordinary school teaching assignments are independent. An exam teacher can see only their own published papers, and removing their exam assignment revokes access.

Dates cannot overlap for the same class/section. Holiday dates require an explicit override reason. Room and invigilator are optional. The simple datesheet sets marks deadlines to seven days after each paper; admin can extend them from Marks Review. Published datesheets and papers with marks are protected. Existing records are preserved.

CSV headers: `admission_no,marks_obtained,theory_marks,practical_marks,attendance_status,public_remark`. Use total obtained marks for theory-only papers, and both component columns for practical papers. Include each roster student once. Zero and Absent are distinct. An invalid upload rolls back all marks.

Verification covers independent exam assignment, drafts and publication, teacher access, duplicate prevention, practical limits and partial drafts, CSV upload, combined multi-subject results, pending results, ranks, report PDF and admin authorization. Desktop/mobile previews are checked without publishing real exams.
