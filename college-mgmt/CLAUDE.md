# College Management System — Developer Context

> **Full guide:** `GUIDE.md` at repo root — read it for complete feature list, workflows, and route maps.

---

## Stack

- **Laravel 13.8** · PHP 8.3+ · SQLite · Bootstrap 5 · Spatie Permissions
- **PDF:** `barryvdh/laravel-dompdf ^3.1` — PDF templates must be standalone HTML, NEVER `@extends()`
- **Layouts:** `layouts/admin` (all staff portals), `layouts/student` (student), `layouts/guest` (public)
- **Branch:** `main` is production; new work on fresh feature branches per department sprint

---

## Development Strategy: Department-by-Department Feature Sprints

**Approach:** One department/user-type at a time, depth-first. Ship complete, useful features for each role before moving to the next.

**Order of departments:**
1. **Student** ← CURRENTLY IN PROGRESS
2. Teacher / Faculty
3. Exam Cell
4. Admission
5. Accounts / Finance
6. HOD / Dean Academics
7. CMC / Placement
8. Admin

**Guiding principle:** Think from the user's daily reality — what does a student/teacher actually need to do every single day? Every feature must solve a real, frequent pain point. No feature theatre.

---

## Roles & Demo Logins (all passwords: `password`)

| Role | Email |
|------|-------|
| admin | admin@college.com |
| admission_head | head@college.com |
| admission_officer | officer@college.com |
| accounts_officer | accounts@college.com |
| dean_academics | dean@college.com |
| hod | hod@college.com |
| program_chair | chair@college.com |
| exam_cell | exam@college.com |
| teacher | ravi@college.com |
| student | aarav@college.com |

---

## Critical SQLite Rules (DO NOT FORGET)

1. **No `HAVING` without `GROUP BY`** — SQLite rejects it. Filter with PHP after `->get()` instead.
2. **Qualify column names in JOINs** — always `table.column` when joining tables with same column name.
3. **No `whereType()` on morphTo** — use `whereHasMorph()` + post-fetch `->load()`.
4. **No ENUM enforcement** — validate in Laravel only.

---

## Route Ordering Rule

Static routes MUST come before wildcard `{param}` routes sharing the same prefix:
```php
Route::get('leads/import', ...);      // FIRST — static
Route::get('leads/export-csv', ...);  // FIRST — static
Route::get('leads/{lead}', ...);      // LAST  — wildcard
```
Registering a static route after a wildcard causes the static route to be shadowed (404).

---

## Polymorphic ApprovalWorkflow Pattern

```php
$query = ApprovalWorkflow::where('approver_role', 'dean_academics')
    ->where('status', 'pending')
    ->with(['approvable', 'approver'])
    ->latest();

// Filter by program (polymorphic)
if ($request->filled('program_id')) {
    $query->whereHasMorph('approvable', [Applicant::class], fn($q) =>
        $q->where('program_id', $request->program_id)
    );
}

$approvals = $query->paginate(20)->withQueryString();

// Load nested relations AFTER fetch (not inside with() closure)
$approvals->getCollection()->each(function ($approval) {
    if ($approval->approvable instanceof Applicant) {
        $approval->approvable->load(['user', 'program', 'batch']);
    }
});
```

---

## Offer Letter Fields (do not use wrong fields)

```php
OfferLetter::create([
    'applicant_id'        => $applicant->id,
    'program_id'          => $applicant->program_id,
    'batch_id'            => $applicant->batch_id,
    'status'              => 'issued',      // NOT 'generated'
    'issued_at'           => now(),         // NOT 'generated_at'
    'issued_by'           => auth()->id(),
    'acceptance_deadline' => now()->addDays(14)->toDateString(),
]);
```

---

## Admission Status Flow

```
draft → submitted → under_review → shortlisted → selected → enrolled
                                              ↘ rejected → waitlisted
```

## Approval Chain (Offer Letters)

```
Admission Head selects applicant
→ Dean Academics approves → OfferLetter created + Program Chair workflow queued
→ Program Chair approves → final
```

---

## Enrollment Number Format

`EnrollmentService::enroll()` auto-generates: **`ENR-YYYY-PROGRAMCODE-#####`**

---

## URL Prefixes by Role

| Portal | Prefix | Layout used |
|--------|--------|-------------|
| Admin | /admin/* | layouts.admin |
| Admission | /admission/* | layouts.admin |
| Accounts | /accounts/* | layouts.admin |
| Dean | /dean/* | layouts.admin |
| HOD | /hod/* | layouts.admin |
| Program Chair | /program-chair/* | layouts.admin |
| Exam Cell | /exam-cell/* | layouts.admin |
| Teacher | /teacher/* | layouts.admin |
| Student | /student/* | layouts.student |
| Parent | /parent/* | layouts.admin |
| Applicant (self-service) | /applicant/* | layouts.admin |
| Public | /apply, /track | layouts.guest |
| Academic team | /academic/* | layouts.admin |

---

## Dev Commands

```bash
php artisan migrate:fresh --seed          # Full reseed
php artisan serve --port=8000             # Dev server
php artisan route:clear                   # Clear route cache
php artisan route:list --name=<prefix>    # List routes

# Playwright tests (screenshots to /tmp/screenshots/)
NODE_PATH=/opt/node22/lib/node_modules node /tmp/test_admission2.js
```

---

## Current Test Status (last run 2026-06-06)

**67 pages tested — 64 HTTP 200 | 0 auth failures | 0 server errors**

Known 404 (not bugs — test URL wrong):
- `/admin/academic-calendar` — correct URL is `/academic/academic-calendars`

---

## Phase 1: Role & Permission Hierarchy — COMPLETE (2026-06-06)

**Status:** Days 1-10 done. All acceptance criteria met.

### Role Hierarchy (highest → lowest)
admin (6) → dean_academics (5) → admission_head (4) → program_chair/hod/admission_officer (3) → accounts_officer/exam_cell/cmc (2) → teacher/faculty (1) → student (0)

Higher roles inherit feature access of all lower roles via `RoleHierarchyService`.

### Key Files Added
- `app/Services/RoleHierarchyService.php` — hierarchy + inheritance logic
- `app/Http/Middleware/ProgramScope.php` — per-program route enforcement
- `app/Http/Middleware/FeatureAccess.php` — feature-code access check (inheritance-aware)
- `app/Providers/AppServiceProvider.php` — @canAccess blade directive
- `database/seeders/RoleFeatureAccessSeeder.php` — 25+ feature codes seeded per role
- `tests/Unit/RoleHierarchyTest.php` — hierarchy logic unit tests
- `tests/Feature/RoleFeatureAccessSeederTest.php` — seeder integration tests

### 25+ Feature Codes
exam.enter_marks, exam.view_results, exam.approve_results, exam.schedule_exam, exam.manage_malpractice,
admission.view_applicants, admission.approve_offers, admission.process_docs, admission.shortlist,
enrollment.enroll_student, enrollment.view_enrolled,
approval.dean_sign_off, approval.chair_sign_off,
curriculum.view, curriculum.edit,
attendance.mark, attendance.view_report,
fee.view_demands, fee.collect_payment, fee.reconcile,
placement.view_drives, placement.create_drive, placement.manage_drive,
report.view_institutional, report.view_program,
user.manage_roles, audit.view_log

---

## Key File Locations

```
routes/web.php                                    — all 497 routes
app/Http/Controllers/Admission/                   — 26 admission controllers
app/Http/Controllers/Departmental/                — Dean, Chair, HOD, ExamCell, Accounts
app/Services/                                     — Grade, Enrollment, Report, Timetable, AdmissionNotification
resources/views/layouts/admin.blade.php           — main staff layout (sidebar)
resources/views/layouts/student.blade.php         — student layout
database/seeders/DemoDataSeeder.php               — all demo data
GUIDE.md                                          — full user & developer guide
```

---

## Academic Phase Implementation Plan

**Status:** Plan created (2026-06-06), awaiting Phase 1 approval

**Timeline:** 6-9 months, 8 phases, ~1,200-1,500 person-days

**Detailed Roadmaps:** (in /college-mgmt/ root)
- `PHASED_IMPLEMENTATION_ROADMAP.md` — Technical blueprint (all features, models, routes)
- `IMPLEMENTATION_SUMMARY.md` — Executive summary for stakeholders
- `IMPLEMENTATION_PATTERNS.md` — Code examples & patterns
- `QUICK_REFERENCE.md` — One-page cheat sheet

**8 Phases:**

| Phase | Theme | Duration | Key Deliverables |
|-------|-------|----------|---|
| 1 | Role & Permission Hierarchy | 2w | Role scoping, permission matrix, audit logging |
| 2 | Role-Specific Dashboards | 2w | 9 dashboards (Dean, Chair, PMC, Exam, HOD, Faculty, CMC, Director, Owner) |
| 3 | Approval Workflows | 2w | Multi-step chains, escalation, SLA tracking |
| 4 | Offer & Enrollment | 2w | Bulk offers, enrollment numbers, portal updates |
| 5 | Academic Lifecycle | 3w | Exams, grades, GPA, promotion, transcripts |
| 6 | Fee Management | 2w | Demands, payments, reconciliation, scholarships |
| 7 | Placement & Career | 2w | Drives, internships, alumni, placement stats |
| 8 | Reporting & Analytics | 2w | AICTE compliance, institutional KPIs, director dashboards |

**New Models (~30):** UserRole, RolePermissionMatrix, CurriculumChange, StudentGrievance, FacultyWorkload, StudentMentorship, PlacementDrive (extended), AlumniProfile, + more

**Key Roles Impacted:** Dean, Program Chair, PMC, Exam Cell, HOD, Faculty, CMC/Placement, Director, Owner

**Status:** All 8 phases COMPLETE and merged to main (2026-06-07).
**Next:** Department-by-department feature depth sprints (see below).

---

## SPRINT: Student Portal — Feature Depth

**Status:** IN PROGRESS  
**Goal:** Make the student portal the single source of truth for a student's entire academic life — attendance, results, fees, assignments, documents, career, and communication — all in one place, zero friction.

---

### What Already Works (Do NOT re-implement)

| Feature | Route | Notes |
|---------|-------|-------|
| Dashboard (4 KPIs) | `student.dashboard` | Attendance %, SGPA, CGPA, Fee balance |
| Attendance view | `student.attendance` | Per-subject breakdown |
| Results / Grades | `student.results` | SGPA/CGPA, semester filter |
| Admit Cards | `student.admit-cards.*` | List + PDF download |
| Subject Registration | `student.subjects.*` | Enroll/drop, 24-credit limit |
| Timetable | `student.timetable` | Weekly grid |
| Transcript PDF | `student.transcript.download` | Official cumulative transcript |
| Fee Status | `student.fees` | Structures, payments, demands, balance |
| Notices | `student.notices.*` | Filtered list + detail |
| Grievances | `student.grievances.*` | Create/list/view |
| Placements | `student.placements.*` | Browse drives, apply, track |
| Profile | `student.profile.*` | View + edit name/phone/password |
| Grade Card PDF | `student.reports.grade-card` | Per-semester PDF |
| Fee Receipt PDF | `student.reports.fee-receipt` | Per-payment PDF |
| Notification Prefs | `student.notifications.*` | 4 email toggles |

---

### Data Model Gaps — Fix These First (Foundational)

These are missing from the schema and block multiple features. Build before any sprint feature.

#### Gap 1: Compulsory vs Elective — `program_subjects` table
Currently subjects are linked to programs directly on the `subjects` table (`program_id`). There is no way to say "this subject is compulsory for all students" vs "students pick 2 from this elective pool". Need a proper curriculum mapping table.

```php
// New migration: create_program_subjects_table
Schema::create('program_subjects', function (Blueprint $table) {
    $table->id();
    $table->foreignId('program_id')->constrained()->cascadeOnDelete();
    $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
    $table->unsignedTinyInteger('term_number');          // which semester/term this subject belongs to
    $table->enum('category', ['core','elective','open_elective','audit','lab','project'])
          ->default('core');
    $table->boolean('is_mandatory')->default(true);      // false = student opts in
    $table->unsignedTinyInteger('elective_group')->nullable(); // students pick N from same group
    $table->unsignedTinyInteger('credits_override')->nullable(); // overrides subject.credits if set
    $table->unsignedSmallInteger('sort_order')->default(0);
    $table->unique(['program_id', 'subject_id', 'term_number']);
    $table->timestamps();
});
```

**Model:** `ProgramSubject` with `program()`, `subject()` relationships.  
**Update:** `Subject::$fillable` — remove `program_id` (managed via junction), keep `department_id`.  
**Update:** `Enrollment` — add `program_subject_id` nullable FK so we know which curriculum slot was filled.  
**Seeders:** When creating subjects, also seed `program_subjects` records.

#### Gap 2: Per-Session Attendance Drill-Down (model is fine, view is incomplete)
The `Attendance` model correctly stores `(student_id, timetable_entry_id, date)` — session-level granularity is there. But `StudentAttendanceController` only shows **subject-level aggregates** (total sessions, present %, etc). Students need to see **exactly which sessions they missed** — date, time slot, subject — to plan condonation requests or dispute errors.

Fix: Add `->with(['timetableEntry.slot', 'timetableEntry.subject'])` to the attendance query and show a session-by-session breakdown view accessible from the subject aggregate row.

#### Gap 3: Session-linked Course Content (Pre-read / Post-read)
Currently study materials would be attached to a subject. But pre-reads and post-reads are tied to a **specific class session** ("before Monday's 10am Data Structures class"). Need an optional `timetable_entry_id + date` on `StudyMaterial` so teachers can push pre-read to students before a specific class and post-read/recording after.

---

### Complete Student Feature Master List

Legend: ✅ EXISTS | 🔧 EXISTS BUT NEEDS FIX | 🆕 NEW | 🔗 CROSS-ROLE (shared with teacher/PMC/admin)

---

#### ACADEMICS — Daily Interaction

| # | Feature | Status | Cross-role? | Notes |
|---|---------|--------|-------------|-------|
| A1 | Dashboard (KPIs + widgets) | 🔧 | — | Needs: deadlines widget, low-att alert, quick actions, credit progress |
| A2 | Personal Timetable (weekly grid) | 🔧 | 🔗 Teacher sees own schedule | Compulsory + elective subjects combined; needs elective awareness |
| A3 | Attendance — subject aggregate view | ✅ | — | Shows % per subject; low-attendance flag |
| A4 | Attendance — session drill-down | 🆕 | — | Show WHICH date/slot was absent/late; link to condonation request |
| A5 | Subject Registration (elective selection) | 🔧 | 🔗 PMC/HOD sets elective pool | Currently no compulsory/elective distinction; needs `ProgramSubject` table |
| A6 | Results — component-wise marks view | 🔧 | — | IA1, IA2, End-Sem breakdown already in DB (AssessmentComponent); not shown to student |
| A7 | Results — cumulative across all terms | ✅ | — | SGPA/CGPA calculated; works |
| A8 | Backlog / Arrear tracker | 🆕 | 🔗 Exam Cell manages | Show all failed subjects, attempt count, eligibility for supplementary |
| A9 | Exam registration (end-sem) | 🆕 | 🔗 Exam Cell approves | Student formally registers; eligibility check: attendance ≥75% + no dues |
| A10 | Admit cards (PDF) | ✅ | — | Exists; works |
| A11 | Official Transcript (PDF) | ✅ | — | Exists; works |
| A12 | Grade card per term (PDF) | ✅ | — | Exists; works |
| A13 | Grade / Marks appeal | 🆕 | 🔗 Teacher/Exam Cell reviews | Student disputes a result with reason; teacher/exam cell can revise |
| A14 | Academic calendar | 🆕 | 🔗 Admin/Dean creates | Exam windows, holidays, fee deadlines, registration periods, results dates |
| A15 | Leave application | 🆕 | 🔗 HOD/Mentor approves | Sick/casual/event leave; attach proof; track approval |
| A16 | Attendance condonation request | 🆕 | 🔗 HOD reviews | When <75% in a subject; attach medical/event certificate |
| A17 | Term promotion status | 🆕 | 🔗 Exam Cell/Admin manages | See if promoted to next term, conditions if any |

---

#### COURSE CONTENT — Multi-role Feature Set 🔗

All of these involve at least Student + Teacher, and visibility for PMC/HOD.

| # | Feature | Student side | Teacher side | PMC/HOD side |
|---|---------|-------------|-------------|-------------|
| C1 | **Study Materials** | Browse/download per subject | Upload PDFs/slides/links | View what's been shared |
| C2 | **Pre-read** (before class) | Listed on session day in timetable | Attach to specific class session | — |
| C3 | **Post-read / Session notes** (after class) | Available after class date | Upload recording/notes after session | — |
| C4 | **Assignments** | View, submit file/text, see grade+feedback | Create, set due date, grade submissions | View completion rate per subject |
| C5 | **Quizzes** (online MCQ/short answer) | Attempt within window, see score | Create, set window, view results | View score distribution |
| C6 | **Coursework / Lab reports** | Submit, track marks | Grade per student | — |
| C7 | **Syllabus / Course outline** | View term syllabus for each subject | Upload/update | Approve curriculum |
| C8 | **Subject Announcements** | Receive per-subject alerts | Post to enrolled students | — |
| C9 | **Discussion / Q&A board** | Ask questions, reply | Answer student questions | Moderation |

**New models needed for C1–C9:**

```
study_materials: id, subject_id, teacher_id, term_id, timetable_entry_id (nullable),
                 session_date (nullable), material_type [pre_read/post_read/notes/reference/syllabus],
                 title, description, file_path, file_size, is_published, published_at

assignments: id, subject_id, teacher_id, term_id, title, description, instructions,
             due_date, max_marks, submission_type [file/text/both], allowed_file_types,
             max_file_size_mb, is_published, late_submission_allowed, late_penalty_pct

assignment_submissions: id, assignment_id, student_id, file_path, text_content,
                        submitted_at, is_late, marks_obtained, feedback,
                        graded_at, graded_by, status [draft/submitted/graded/returned]

quizzes: id, subject_id, teacher_id, term_id, title, description, duration_minutes,
         start_at, end_at, total_marks, passing_marks, max_attempts, shuffle_questions,
         show_result_immediately, is_published

quiz_questions: id, quiz_id, question_text, question_type [mcq/true_false/short_answer],
                marks, sort_order, explanation (for post-quiz review)

quiz_options: id, question_id, option_text, is_correct, sort_order

quiz_attempts: id, quiz_id, student_id, started_at, submitted_at, score,
               is_completed, attempt_number

quiz_answers: id, attempt_id, question_id, selected_option_id, text_answer, is_correct, marks_awarded

subject_announcements: id, subject_id, teacher_id, term_id, title, body,
                       is_pinned, published_at
```

---

#### FINANCE

| # | Feature | Status | Notes |
|---|---------|--------|-------|
| F1 | Fee status — view demands + payments | ✅ | Works; FeeDemand is source of truth |
| F2 | Fee payment — manual UTR/proof submission | 🆕 | Student submits payment proof; Accounts verifies |
| F3 | Fee receipt download (PDF) | ✅ | Works |
| F4 | Fee structure breakdown | 🔧 | Show what each component (tuition/exam/library) costs |
| F5 | Scholarship — view available | 🆕 | 🔗 Admin/Dean manages scholarships |
| F6 | Scholarship — apply + track | 🆕 | 🔗 Accounts processes, Dean approves |
| F7 | Fine / penalty details | 🔧 | Show late fee, library fines in one place |
| F8 | Payment history — all transactions | ✅ | Works |

```
fee_payment_requests: id, student_id, fee_demand_id, amount, payment_method,
                      bank_name, transaction_ref, proof_path, submitted_at,
                      verified_by, verified_at, status [pending/verified/rejected], notes

scholarships: id, name, type [merit/need/government/institutional/sports],
              program_id (nullable), amount, description, eligibility_criteria,
              application_deadline, max_recipients, is_active

scholarship_applications: id, scholarship_id, student_id, term_id,
                          cgpa_at_application, reason, documents_path,
                          status [pending/shortlisted/approved/rejected/disbursed],
                          reviewed_by, review_note, disbursed_at, disbursed_amount
```

---

#### CAREER

| # | Feature | Status | Notes |
|---|---------|--------|-------|
| CR1 | Placement drives — browse + apply | ✅ | Works |
| CR2 | My placement applications — track | ✅ | Works |
| CR3 | Internship — view assigned/active | 🔧 | InternshipController exists (CMC creates); student view missing |
| CR4 | Resume / CV builder | 🆕 | Auto-fill education from DB; add skills/experience/projects |
| CR5 | Alumni — browse same-program alumni | 🆕 | Filter by graduation year, company; request connection |
| CR6 | Career events / workshops | 🆕 | Register for seminars, mock interviews |

```
student_resumes: id, student_id, headline, objective, skills (JSON array), 
                 languages (JSON), projects (JSON), certifications (JSON),
                 custom_resume_path, is_complete, last_updated_at

career_events: id, title, event_type [seminar/mock_interview/workshop/company_visit],
               organizer_id, date, venue, description, seats, registration_deadline

career_event_registrations: id, event_id, student_id, registered_at, attended (bool)
```

---

#### WELLBEING & SUPPORT

| # | Feature | Status | Notes |
|---|---------|--------|-------|
| W1 | Grievances — create/track | ✅ | Works |
| W2 | Grievances — add follow-up / close | 🔧 | Currently read-only after submit; add comment/close |
| W3 | Mentor — view assigned faculty mentor | 🆕 | 🔗 Mentor is a teacher; assigned by HOD |
| W4 | Mentor — request meeting | 🆕 | 🔗 Teacher confirms/declines meeting request |
| W5 | Mentor — message thread | 🆕 | Simple text thread between student and mentor |
| W6 | Course / Teacher feedback | 🆕 | 🔗 At term-end; anonymous; HOD/Dean sees results |
| W7 | Health / medical record access | 🆕 | (Low priority, Phase 3 or later) |

```
mentor_assignments: id, student_id, teacher_id (mentor), assigned_by, assigned_at, is_active
  (add mentor_id FK to students table for quick access)

mentor_meetings: id, mentor_assignment_id, student_id, teacher_id, 
                 requested_at, scheduled_at, duration_minutes, agenda,
                 status [requested/confirmed/completed/cancelled], notes

mentor_messages: id, mentor_assignment_id, sender_id (user_id), body, 
                 sent_at, read_at

course_feedback: id, student_id, subject_id, teacher_id, term_id,
                 teaching_rating (1-5), content_rating (1-5), engagement_rating (1-5),
                 overall_rating (1-5), comments, is_anonymous, submitted_at
  (unique: student_id + subject_id + term_id — one per subject per term)
```

---

#### DOCUMENTS

| # | Feature | Status | Notes |
|---|---------|--------|-------|
| D1 | Request bonafide certificate | 🆕 | Admin generates PDF, student downloads |
| D2 | Request fee paid letter | 🆕 | Admin generates, includes term-wise paid amounts |
| D3 | Request character certificate | 🆕 | Requires HOD approval before admin generates |
| D4 | ID card download (digital) | 🆕 | Auto-generated PDF with photo, enrollment number, QR code |
| D5 | My uploaded documents vault | 🆕 | Personal docs: Aadhar, 10th/12th marksheets, etc. |

```
document_requests: id, student_id, document_type [bonafide/fee_letter/character/migration/noc],
                   purpose, additional_info, status [pending/approved/rejected/ready],
                   requested_at, reviewed_by, fulfilled_at, output_path, notes

student_documents: id, student_id, document_name, document_type, file_path,
                   uploaded_at, is_verified, verified_by
```

---

#### PROFILE & ACCOUNT

| # | Feature | Status | Notes |
|---|---------|--------|-------|
| P1 | Profile — view + edit name/phone | ✅ | Works |
| P2 | Profile — photo upload | 🔧 | `photo` field exists on Student model; no upload UI |
| P3 | Profile — guardian/emergency contacts | 🔧 | Fields exist (guardian_name, guardian_phone); no edit UI |
| P4 | Notification preferences | ✅ | 4 email toggles; works |
| P5 | Change password | ✅ | Works via profile edit |
| P6 | Academic summary card | 🆕 | One-page view: program, batch, term, CGPA, credits, mentor name |

---

### Cross-Role Feature Matrix

Features that span multiple portals — must be designed consistently from day one.

| Feature | Student | Teacher | PMC/Chair | HOD | Exam Cell | Admin |
|---------|---------|---------|-----------|-----|-----------|-------|
| Timetable | View personal | View personal schedule | View program timetable | View dept timetable | — | Create/manage |
| Attendance | View own sessions | Mark for each class | View program-level stats | View dept alerts | Export | Override/audit |
| Assignments | Submit + view grade | Create + grade | View completion % | View dept summary | — | Audit |
| Study Materials | Download | Upload/manage | Approve syllabus | Monitor | — | Audit |
| Quizzes | Attempt | Create/grade | View analytics | — | — | Audit |
| Leave | Apply | Approve (for mentees) | View program absences | Approve (dept) | — | Override |
| Grievances | Raise | — | — | Resolve/escalate | — | Final escalation |
| Academic Calendar | View | View | View + suggest | View + suggest | Manage exam dates | Create/manage |
| Results | View own | Enter marks | View program stats | View dept stats | Publish/audit | Override |
| Course Feedback | Submit | View own ratings | View program ratings | View dept ratings | — | View all |
| Scholarships | Apply | — | — | Recommend | — | Approve/disburse |
| Documents | Request | — | — | Approve character cert | — | Fulfil + generate |
| Mentor | View mentor | See mentees + messages | — | Assign mentors | — | — |
| Placements | Apply to drives | — | Track students | — | — | Manage drives (CMC) |

---

### Build Sequence (Student Sprint Plan)

**Foundation first (no UI, just schema + models):**
- [ ] `program_subjects` table + `ProgramSubject` model + seeder
- [ ] `study_materials` table + model
- [ ] `assignments` + `assignment_submissions` tables + models
- [ ] `quizzes` + `quiz_questions` + `quiz_options` + `quiz_attempts` + `quiz_answers` tables + models
- [ ] `leave_applications` table + model
- [ ] `academic_events` table + model
- [ ] `attendance_condonations` table + model
- [ ] `document_requests` + `student_documents` tables + models
- [ ] `mentor_assignments` + `mentor_meetings` + `mentor_messages` tables + models
- [ ] `course_feedback` table + model
- [ ] `fee_payment_requests` table + model
- [ ] `scholarships` + `scholarship_applications` tables + models
- [ ] `student_resumes` table + model
- [ ] Add `mentor_id` FK to `students` table

**Sprint 1 — Daily use (what a student opens every day):**
- [ ] S1-1: Dashboard redesign — deadlines widget, credit progress, low-att banner, quick actions
- [ ] S1-2: Timetable — fix to show compulsory vs elective labels; today's-classes widget
- [ ] S1-3: Attendance drill-down — session-by-session missed classes view
- [ ] S1-4: Course content hub — study materials (pre-read / post-read / notes) per subject
- [ ] S1-5: Assignments — view + submit + see grade/feedback
- [ ] S1-6: Academic calendar — monthly view of all important dates
- [ ] S1-7: Leave application — submit + track status
- [ ] S1-8: Subject announcements — per-subject teacher announcements visible to enrolled students

**Sprint 2 — Weekly use:**
- [ ] S2-1: Component-wise marks (IA1/IA2/End-Sem) visible in Results page
- [ ] S2-2: Backlog / arrear tracker — failed subjects across all terms
- [ ] S2-3: Attendance condonation request
- [ ] S2-4: Fee payment proof submission (manual UTR) + status tracking
- [ ] S2-5: Document requests — bonafide, fee letter
- [ ] S2-6: Digital ID card download
- [ ] S2-7: Profile — photo upload + guardian contact edit
- [ ] S2-8: Grievances — add follow-up message + close resolved

**Sprint 3 — High value, less frequent:**
- [ ] S3-1: Online quizzes — attempt, see score, review answers
- [ ] S3-2: Exam registration with eligibility check
- [ ] S3-3: Grade / marks appeal
- [ ] S3-4: Scholarship — view + apply + track
- [ ] S3-5: Mentor — view assignment, message thread, request meeting
- [ ] S3-6: Course / teacher feedback at term-end
- [ ] S3-7: Resume / CV builder for placements
- [ ] S3-8: Career events — browse + register

**Sprint 4 — Nice to have:**
- [ ] S4-1: Discussion / Q&A board per subject
- [ ] S4-2: Character certificate request (requires HOD approval)
- [ ] S4-3: Internship view (student sees own internship records)
- [ ] S4-4: Alumni browse + connect
- [ ] S4-5: Term promotion status view
- [ ] S4-6: Academic summary card (one-page printable student card)

---

### Known Bugs to Fix (Do Before Sprint 1)

1. **Sidebar duplicate** — Grievances appears twice in `layouts/student.blade.php`
2. **Fee balance inconsistency** — Dashboard uses `max(fee_due, demands)` which can mislead; consolidate to `FeeDemand` as single source
3. **Subject registration** — `term_id→semester_id` fallback shows all subjects if no mapping; fix after `ProgramSubject` table added
4. **Attendance view** — Queries attendance without `timetableEntry` eager load; causes N+1 queries at scale
5. **Results page** — `AssessmentComponent` data exists in DB but is not shown to students; surface IA1/IA2/End-Sem breakdown

---

### Student Portal — File Locations

```
app/Http/Controllers/Student/          — all student controllers (14 files)
resources/views/student/               — all student views (18 templates)
resources/views/layouts/student.blade.php — student layout + sidebar nav
routes/web.php                         — student routes (~line 490-580, student. prefix)
app/Models/                            — Enrollment, Attendance, Exam, ExamResult, AssessmentComponent
app/Services/GradeService.php          — SGPA/CGPA calculation
app/Services/TimetableService.php      — timetable grid builder
database/migrations/                   — see Gap 1 above for ProgramSubject migration
```
