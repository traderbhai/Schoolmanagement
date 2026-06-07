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

### Sprint 1 — High Impact, Daily Use (BUILD NEXT)

These are things a student touches every single day or week.

#### S1-1: Dashboard Redesign (Upgrade existing)
**Problem:** Current dashboard shows 4 KPIs + timetable + notices. Missing: upcoming deadlines, academic progress, low-attendance warnings, quick actions.
**Add to dashboard controller + view:**
- Academic Progress Bar — credits earned vs. total required for program
- Upcoming Deadlines widget — exams (next 7 days) + assignment due dates + fee due dates, unified sorted list
- Low Attendance Alert — red banner if any subject < 75% attendance
- Quick Actions row — "Pay Fees", "Download Transcript", "Submit Assignment", "Apply for Leave"
- Recent Activity feed — last 5 events (result published, notice added, grievance update, payment received)
- Today's class schedule highlight (pull from timetable, show next class)

#### S1-2: Assignment Management
**Problem:** Teachers assign homework/projects but students have no portal to view or submit them.
**New models:** `Assignment` (subject_id, teacher_id, title, description, due_date, max_marks, attachment_path, term_id), `AssignmentSubmission` (assignment_id, student_id, file_path, submitted_at, marks_obtained, feedback, graded_at, graded_by)
**Student routes:**
- `GET student/assignments` — list all assignments for enrolled subjects, filter by status (pending/submitted/graded), due date
- `GET student/assignments/{assignment}` — view details + submission form
- `POST student/assignments/{assignment}/submit` — upload file or text submission
- `GET student/assignments/{assignment}/submission` — view own submission + grade/feedback
**Teacher routes (in teacher sprint, scaffold now):**
- `POST teacher/assignments` — create assignment for a subject
- `GET teacher/assignments/{assignment}/submissions` — view all submissions
- `POST teacher/assignments/submissions/{submission}/grade` — enter marks + feedback
**Migration:** `assignments` table + `assignment_submissions` table

#### S1-3: Study Materials / Resources
**Problem:** Students have no way to download lecture notes, syllabuses, or reference materials from within the portal. Everything is on WhatsApp.
**New model:** `StudyMaterial` (subject_id, teacher_id, title, description, file_path, file_type, term_id, is_published)
**Student routes:**
- `GET student/materials` — list materials for enrolled subjects, filter by subject
- `GET student/materials/{material}/download` — download file
**Teacher routes (scaffold now):**
- `POST teacher/materials` — upload material for a subject
- `GET/PATCH/DELETE teacher/materials/{material}` — manage
**Migration:** `study_materials` table

#### S1-4: Academic Calendar
**Problem:** Students don't know important dates — exam windows, fee deadlines, holiday schedule, registration periods. They rely on notice board, WhatsApp, word of mouth.
**Use existing `AcademicCalendar` model if present, else create:**
**New model:** `AcademicEvent` (title, event_type [exam_window/holiday/fee_deadline/registration/result/cultural/other], start_date, end_date, description, is_public, program_id nullable)
**Student route:**
- `GET student/calendar` — monthly/list view of events for their program + institution-wide
**Admin route:** `GET/POST/PATCH/DELETE admin/academic-events` — CRUD for events
**Migration:** `academic_events` table

#### S1-5: Leave Application
**Problem:** Students need to apply for leave (sick/personal/event) but currently have to physically visit the office or WhatsApp the faculty. No tracking exists.
**New model:** `LeaveApplication` (student_id, leave_type [sick/casual/event/exam_duty], from_date, to_date, reason, status [pending/approved/rejected], reviewed_by, review_note, attachment_path)
**Student routes:**
- `GET student/leaves` — list my leave applications with status
- `GET student/leaves/create` — apply form
- `POST student/leaves` — submit application
- `GET student/leaves/{leave}` — view detail + approval status
**HOD/Teacher routes (scaffold now):**
- `GET hod/leaves` — list pending leave applications for department students
- `POST hod/leaves/{leave}/approve` / `/reject` — action
**Migration:** `leave_applications` table

---

### Sprint 2 — Important, Weekly Use (PLAN NEXT)

#### S2-1: Backlog / Arrear Management
- View failed subjects across all semesters in one place
- Re-exam eligibility check (attendance + prior attempt count)
- Apply for supplementary/back exam registration
- Track arrear clearance progress toward degree completion

#### S2-2: Document Requests
- Request bonafide certificate (for bank, visa, scholarship purposes)
- Request fee paid confirmation letter
- Request character certificate
- ID card reprint request
- Admin fulfils request, student downloads approved document PDF
- `document_requests` table: student_id, document_type, purpose, status, fulfilled_by, document_path

#### S2-3: Scholarship Management (Student side)
- View available scholarships (government, institutional, merit)
- Apply for scholarship with supporting documents
- Track application status and disbursement
- `scholarships` table + `scholarship_applications` table

#### S2-4: Attendance Condonation Request
- When attendance < 75% in a subject, student can submit condonation request with reason (medical/sports/event)
- Attach supporting document (medical certificate, participation certificate)
- HOD/faculty reviews and approves/rejects
- `attendance_condonations` table

#### S2-5: Fee Payment (Online)
- Currently: only VIEW fee status. Students cannot pay.
- Add manual payment recording (student logs payment reference/UTR, accounts verifies)
- Prepare for payment gateway (Razorpay/PayU) integration — create payment session structure
- `fee_payment_requests` table: student_id, fee_demand_id, amount, payment_method, transaction_ref, proof_path, status

---

### Sprint 3 — High Value, Less Frequent

#### S3-1: Faculty Mentor Interaction
- Each student has an assigned faculty mentor (`mentor_id` on students table)
- Student can send messages to mentor, view conversation history
- Mentor can schedule 1:1 meetings
- `mentor_messages` table + `mentor_meetings` table

#### S3-2: Course / Teacher Feedback
- At end of each semester, student rates each subject teacher (1-5 stars + text)
- Anonymous by default (admin can de-anonymise)
- Results visible only to HOD and Dean
- `course_feedback` table: student_id, subject_id, teacher_id, term_id, ratings (JSON), comments, submitted_at

#### S3-3: Library Module
- View books issued to me
- Due date and fine status
- Request to renew online
- Search catalogue (books available/checked out)
- `library_books` table + `library_issues` table

#### S3-4: Event Registration
- View upcoming college events (technical fest, cultural, workshops, seminars)
- Register for events (seat-limited)
- View registered events, download participation certificate
- `events` table + `event_registrations` table

#### S3-5: Resume / CV Management (for Placement)
- Build resume within portal (education auto-filled, add skills/projects/achievements)
- Upload custom resume PDF (replaces builder output)
- Resume used automatically when applying for placement drives
- `student_resumes` table: student_id, headline, skills, projects, achievements, resume_pdf_path

---

### Known Issues to Fix in Student Portal

1. **Timetable source mismatch** — Dashboard uses `TimetableSlot` model; `student.timetable` view uses old `TimetableEntry` model. Unify to `TimetableSlot`.
2. **Grievance — no update/close** — Students can't add follow-up comments or close a resolved grievance.
3. **Subject Registration** — `term_id`→`semester_id` mapping may show wrong subjects if mapping is missing.
4. **Fee balance logic** — Uses `max()` of two sources which can be inconsistent; consolidate to FeeDemand as single source of truth.
5. **Sidebar duplicate** — Grievances appears twice in `layouts/student.blade.php` sidebar.

---

### Student Portal — File Locations

```
app/Http/Controllers/Student/          — all student controllers
resources/views/student/               — all student views
resources/views/layouts/student.blade.php — student layout + sidebar
routes/web.php                         — student routes (search "student." prefix, ~line 490-580)
```
