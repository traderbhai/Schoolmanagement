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
1. **Student** ✅ COMPLETE — merged to main (PR #15, 2026-06-07)
2. **PMC (Program Management Cell / Program Chair)** ✅ COMPLETE — merged to main (PR #16, 2026-06-07)
3. **Teacher / Faculty** ✅ COMPLETE — merged to main (PR #17, 2026-06-07)
4. **Exam Cell** ✅ COMPLETE — merged to main (PR #18, 2026-06-07)
5. **Accounts / Finance** ✅ COMPLETE — merged to main (PR #18, 2026-06-07)
6. **HOD** ✅ COMPLETE — merged to main (PR #18, 2026-06-07)
7. **CMC / Placement** ✅ COMPLETE — merged to main (PR #18, 2026-06-07)
8. **Admin** ✅ COMPLETE — 235+ routes covering all management functions

**Guiding principle:** Think from the user's daily reality — what does a student/teacher actually need to do every single day? Every feature must solve a real, frequent pain point. No feature theatre.

---

## Roles & Demo Logins (all passwords: `password`)

| Role | Email |
|------|-------|
| admin | admin@demo.edu |
| admission_head | head@college.com |
| admission_officer | officer@college.com |
| accounts_officer | accounts@college.com |
| dean_academics | dean@college.com |
| hod | hod@college.com |
| program_chair | chair@college.com |
| cmc | cmc@college.com |
| director | director@college.com |
| exam_cell | exam@college.com |
| teacher | anjali@demo.edu |
| student | arjun.k@demo.edu |

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
| Program Chair / PMC | /program-chair/* | layouts.admin |
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
php artisan migrate --force               # Run new migrations (production mode)

# Graphify (for architecture analysis)
graphify update .                          # Update graph cache (no API cost)
graphify query "Show Model A relationships"  # Query architecture
open graphify-out/graph.html              # Open interactive visualization
```

---

## Graphify Architecture Analysis Tool

**REQUIRED for all development.** Graphify transforms the codebase into a queryable knowledge graph, reducing AI chat token usage by 50-70%.

**Setup:**
```bash
uv tool install graphifyy
graphify install
```

**Daily usage:**
```bash
# Update graph after code changes
graphify update .

# Query architecture before implementing features
graphify query "What models handle [domain]?"

# View communities in browser
open graphify-out/graph.html
```

**Why it matters:**
- Replaces 100+ tokens of "describe your architecture" explanation
- Makes better design decisions with graph context
- Identifies import cycles and weak dependencies
- Tracks architecture evolution over sprints

**See:** `GRAPHIFY_WORKFLOW.md` for detailed protocol on using graphify in Claude chats.

---

## Current Test Status (last run 2026-06-06)

**67 pages tested — 64 HTTP 200 | 0 auth failures | 0 server errors**

---

## Key File Locations

```
routes/web.php                                    — all routes (~670 lines)
app/Http/Controllers/Student/                     — 25 student controllers
app/Http/Controllers/Departmental/                — Dean, Chair, HOD, ExamCell, Accounts, CMC, Alumni
app/Http/Controllers/Admission/                   — 26 admission controllers
app/Services/                                     — Grade, Enrollment, Report, Timetable, AdmissionNotification
resources/views/layouts/admin.blade.php           — main staff layout (sidebar)
resources/views/layouts/student.blade.php         — student layout (full sidebar: all 4 sprints)
resources/views/student/                          — 40+ student view templates (Sprints 1-4)
database/seeders/DemoDataSeeder.php               — all demo data
GUIDE.md                                          — full user & developer guide
```

---

## COMPLETED: Student Portal (Sprints 1–4) — merged to main

**PR #15** merged 2026-06-07. All student-facing features are complete.

### What Was Built

| Sprint | Key Features |
|--------|-------------|
| S1 | Dashboard (KPI cards, low-att banner, deadlines widget), timetable, session-level attendance drill-down, course content hub (pre-read/post-read/notes), assignments, academic calendar, leave applications |
| S2 | Component-wise marks (IA1/IA2/End-Sem), backlog tracker, attendance condonation, fee payment proof, document requests, profile (photo upload, guardian), grievance comments |
| S3 | Exam registration (eligibility check), marks appeals, scholarships, mentor (messages + meetings), course feedback, resume builder, career events |
| S4 | Discussion board per subject, internship view, alumni network, term promotion status, academic summary card (printable) |

### New Tables Added (Student sprints)
`program_subjects`, `student_subject_enrollments`, `study_materials`, `assignments`, `assignment_submissions`, `quizzes`, `quiz_questions`, `quiz_options`, `quiz_attempts`, `quiz_answers`, `leave_applications`, `academic_events`, `subject_announcements`, `mentor_meetings`, `mentor_messages`, `course_feedback`, `attendance_condonations`, `fee_payment_requests`, `document_requests`, `grievance_comments`, `student_scholarship_applications`, `marks_appeals`, `exam_registrations`, `student_resumes`, `career_events`, `career_event_registrations`, `subject_discussions`, `subject_discussion_replies`

---

## NEXT SPRINT: PMC (Program Management Cell) Portal — Feature Depth

**Status:** PLANNING COMPLETE — ready to implement  
**Branch to create:** `claude/pmc-sprint`  
**Route prefix:** `/program-chair/*` (name prefix: `chair.`)  
**Layout:** `layouts.admin`  
**Controller directory:** `app/Http/Controllers/Departmental/`  
**Main controller:** `ProgramChairController.php` (already exists — extend it)

### What Already Exists (Do NOT re-implement)

| Feature | Route | Notes |
|---------|-------|-------|
| Dashboard (basic) | `chair.dashboard` | KPI tiles + recent exams; needs full rebuild |
| Student list | `chair.students` | Browse + filter by batch/status |
| Curriculum view | `chair.curriculum` | Subjects grouped by program + term; read-only |
| Timetable view | `chair.timetable` | Read-only grid grouped by day |
| Exam list | `chair.exams` | Upcoming + past; read-only |
| Approval workflows | `chair.approvals` | Approve/reject admission offer letters |

### Existing Schema (understand before adding)

```
timetable_entries: id, semester_id, course_id, subject_id, teacher_id, classroom_id,
                   timetable_slot_id, day_of_week, is_active, program_id, term_id
                   (+ batch_id was referenced in view but check if column exists)

timetable_slots: id, name, start_time, end_time, is_break, sort_order, is_active

program_subjects: id, program_id, subject_id, term_id, type, elective_group,
                  credits, max_elective_choices, is_active

teachers: id, user_id, department_id, employee_id, designation, qualification,
          specialization, phone, employment_type, status, photo

classrooms: id, name, room_number, capacity, type, building, floor,
            has_projector, has_lab, is_active

subjects: id, department_id, name, code, credits, type, hours_per_week,
          is_active, program_id, term_number
```

---

### PMC Role — Who They Are & What They Do Daily

The PMC (Program Management Cell / Program Chair) is the **operational backbone of a program**. Unlike the HOD (who manages a department) or the Dean (strategic oversight), the PMC is hands-on academic management:

- Owns **one or more programs** (e.g., B.Tech CSE, MBA Finance)
- Is accountable for **every student's academic journey** in their program
- Coordinates between students, faculty, exam cell, accounts, and administration
- Has **no authority over HR** (that's HOD) but full authority over **academic operations**

---

### PMC Feature Master List

Legend: ✅ EXISTS (basic) | 🔧 EXISTS BUT INCOMPLETE | 🆕 NEW

---

#### CURRICULUM MANAGEMENT

| # | Feature | Status | Notes |
|---|---------|--------|-------|
| CM1 | View curriculum by term | ✅ | Subjects grouped by program + term; read-only |
| CM2 | Add/edit subjects to program-term | 🆕 | Create ProgramSubject entries; set type (core/elective/lab/project) |
| CM3 | Manage elective pools | 🆕 | Define elective groups; set how many students must pick from each group; set min/max credits |
| CM4 | Set term credit limits | 🆕 | Max credits a student can register per term (e.g., 24 credits) |
| CM5 | Curriculum change requests | 🔧 | `CurriculumChange` model exists via `academic/curriculum-changes`; PMC should initiate + track |
| CM6 | Subject-to-faculty assignment | 🆕 | Assign which teacher teaches which subject in which term; basis for timetable |
| CM7 | Course outline / syllabus upload | 🆕 | Upload PDF syllabus per subject per term; visible to students |
| CM8 | Prerequisite management | 🆕 | Mark which subjects require prior completion (e.g., DSA requires C Programming) |

---

#### TIMETABLE MANAGEMENT (Core PMC Responsibility)

This is the most complex feature. A real timetable engine must handle:

**Hard Constraints (must not be violated):**
- No teacher assigned to two sessions at the same time
- No classroom double-booked for the same slot
- No batch has two subjects at the same time
- Lab sessions need rooms with `has_lab = true`
- Room `capacity` ≥ batch size

**Soft Constraints (try to satisfy):**
- Teacher availability preferences (not before 9am, not on Friday afternoon)
- Spread subjects evenly across the week (don't stack 4 lectures Monday morning)
- Back-to-back labs preferred (2-hour contiguous blocks)
- Core subjects earlier in the day, electives/labs later

| # | Feature | Status | Notes |
|---|---------|--------|-------|
| TT1 | Timetable view (read-only grid) | ✅ | Already exists; day-grouped; needs slot-grid format |
| TT2 | Timetable builder — drag-and-drop slot assignment | 🆕 | Assign subject+teacher+room to day+slot for a batch+term; conflict detection on save |
| TT3 | Teacher availability management | 🆕 | Each teacher sets preferred/blocked slots per week; PMC sees when building timetable |
| TT4 | Conflict detection engine | 🆕 | Real-time check: teacher clash, room clash, batch clash; return specific conflict message |
| TT5 | Timetable publish / draft state | 🆕 | Draft → Published; students/teachers only see published timetable |
| TT6 | Timetable version history | 🆕 | Keep old published timetables; allow rollback; show "effective from" date |
| TT7 | Substitute / replacement session | 🆕 | When a teacher is absent, PMC assigns substitute or cancels session with notice |
| TT8 | Extra / makeup class scheduling | 🆕 | Schedule one-off additional sessions outside the regular timetable |
| TT9 | Timetable export (PDF + Excel) | 🆕 | Print-ready timetable for each batch; teacher-wise schedule export |
| TT10 | Room utilization report | 🆕 | Which rooms are over/under-utilized; free slots per room |

**New tables needed for timetable:**
```
teacher_availability: id, teacher_id, term_id, day_of_week, timetable_slot_id,
                      availability_type [available/unavailable/preferred], notes

timetable_versions: id, program_id, term_id, batch_id, version_number,
                    status [draft/published/archived], published_at, published_by,
                    effective_from, notes

timetable_substitutions: id, original_entry_id, substitute_teacher_id, date,
                         reason, status [scheduled/cancelled], notified_at
```

**Key rule:** `timetable_entries` already has the right shape. Add `timetable_version_id` FK and `batch_id` (check if missing). The builder assigns entries; publish pushes version to active.

---

#### FACULTY COORDINATION

| # | Feature | Status | Notes |
|---|---------|--------|-------|
| FC1 | Faculty workload view | 🆕 | Hours per week per teacher for the term; flag overloaded (>18 hrs) or underloaded |
| FC2 | Subject-faculty assignment | 🆕 | Map teacher → subject → term → batch (one teacher per subject-batch-term) |
| FC3 | Faculty attendance summary | 🆕 | How many sessions each teacher has conducted vs scheduled; flag absenteeism |
| FC4 | Leave approval for faculty (from PMC perspective) | 🆕 | PMC sees teacher leave requests; ensures coverage is arranged |
| FC5 | Course delivery tracking | 🆕 | How many of the planned syllabus topics have been covered; % completion per subject |
| FC6 | Faculty feedback summary (from students) | 🆕 | Aggregated anonymous ratings per teacher per subject; visible only to PMC + HOD + Dean |
| FC7 | Faculty communication (broadcast) | 🆕 | PMC sends notice/announcement to all faculty in their program |

---

#### STUDENT MANAGEMENT (PMC's Central Responsibility)

| # | Feature | Status | Notes |
|---|---------|--------|-------|
| SM1 | Student list with full academic status | 🔧 | Exists; add CGPA, attendance %, arrear count, fee status columns |
| SM2 | At-risk student dashboard | 🆕 | Students who are: attendance < 75% in any subject, CGPA < 5.0, have arrears, or have dues |
| SM3 | Elective registration management | 🆕 | View which students picked which electives; override/fix registrations; enforce group limits |
| SM4 | Mentor assignment | 🆕 | Assign faculty mentors to students; bulk assign by batch or roll number range |
| SM5 | Leave application approvals | 🆕 | PMC approves/rejects student leave requests; can delegate to teacher |
| SM6 | Attendance condonation review | 🆕 | Review student condonation requests; approve with session count; forward to Dean if > threshold |
| SM7 | Grievance management (program-level) | 🆕 | See all open grievances in their program; assign staff to resolve; escalate to HOD |
| SM8 | Student promotion decisions | 🆕 | View term-end promotion eligibility; flag detained students; process batch promotions |
| SM9 | Batch-level communication | 🆕 | Send announcements to entire batch or specific section |
| SM10 | Detained/ATKT students register | 🆕 | List of students with arrears, their subject-wise attempt history, supplementary eligibility |

---

#### ACADEMIC CALENDAR MANAGEMENT

| # | Feature | Status | Notes |
|---|---------|--------|-------|
| AC1 | Create academic events | 🆕 | Holidays, exam windows, assignment deadlines, registration periods, result dates, fee due dates |
| AC2 | Calendar visible to students/faculty | 🆕 | Published events flow to `student.calendar.index` (already wired) |
| AC3 | Term/semester planning | 🆕 | Define term start/end dates, teaching weeks, exam week; link to program |
| AC4 | Exam schedule coordination | 🆕 | PMC proposes exam dates per subject → Exam Cell publishes; no direct conflict across subjects |

---

#### ASSESSMENT & MARKS MANAGEMENT

| # | Feature | Status | Notes |
|---|---------|--------|-------|
| AM1 | Assessment component setup | 🆕 | For each subject-term: define IA1 (20%), IA2 (20%), End-Sem (60%) weights |
| AM2 | Marks submission tracking | 🆕 | Track which teachers have submitted IA marks vs pending; send reminders |
| AM3 | Marks appeal review | 🆕 | PMC sees all marks appeals in their program; forwards to teacher or overrides |
| AM4 | Grade moderation | 🆕 | If average marks < threshold, PMC can trigger moderation; log reason |
| AM5 | Result publication workflow | 🆕 | PMC certifies results are correct → sends to Exam Cell for official publication |

---

#### ELECTIVE MANAGEMENT (Unique to PMC)

This is a key PMC function. Students pick electives from a pool; PMC must:
1. Float electives (announce which electives are available this term)
2. Set registration window (dates when students can pick)
3. See demand (how many students want each elective)
4. Decide section allocation (if 60 students want one elective, split into two sections)
5. Close registration and finalize elective rosters
6. Handle exceptions (student wanting to change elective post-deadline)

| # | Feature | Status | Notes |
|---|---------|--------|-------|
| EL1 | Float electives for a term | 🆕 | Mark program_subjects records as elective+published; set registration window |
| EL2 | View elective demand | 🆕 | Real-time count of how many students have registered for each elective |
| EL3 | Set section capacity for electives | 🆕 | If demand > capacity, PMC decides which students get which section |
| EL4 | Close elective registration | 🆕 | Lock registrations; auto-notify students of their confirmed elective |
| EL5 | Override individual elective registration | 🆕 | Change a specific student's elective assignment (with reason log) |
| EL6 | Elective minimum enrollment check | 🆕 | If < N students register for an elective, cancel it and redirect students |

**New table:**
```
elective_registration_windows: id, program_id, term_id, elective_group,
                                 opens_at, closes_at, max_per_student,
                                 status [draft/open/closed], created_by
```

---

#### REPORTING & ANALYTICS

| # | Feature | Status | Notes |
|---|---------|--------|-------|
| R1 | Program health dashboard | 🔧 | Exists but basic; needs: term-over-term CGPA trend, attendance trend, pass rate |
| R2 | Subject-wise performance report | 🆕 | Average marks per subject; distribution chart; compare across batches |
| R3 | Faculty performance report | 🆕 | Sessions conducted %, student feedback ratings, assignment grading turnaround |
| R4 | Placement readiness report | 🆕 | Students with CGPA ≥ 6.5, no arrears, resume complete — eligible for on-campus drives |
| R5 | Attendance defaulter report | 🆕 | Students below 75% in any subject; exportable with contact info for parent notification |
| R6 | Term-end summary report (PDF) | 🆕 | One PDF with: enrollment count, pass/fail stats, CGPA distribution, top performers |
| R7 | AICTE/regulatory compliance report | 🆕 | Teaching days completed, syllabus coverage %, exam compliance |

---

### Build Sequence (PMC Sprint Plan)

#### Foundation (schema + models — build first, no UI)

New migrations needed:
```
2026_06_07_800000_create_teacher_availability_table.php
2026_06_07_800001_create_timetable_versions_table.php
2026_06_07_800002_create_timetable_substitutions_table.php
2026_06_07_800003_create_elective_registration_windows_table.php
2026_06_07_800004_add_batch_id_to_timetable_entries.php  (if missing)
2026_06_07_800005_create_subject_faculty_assignments_table.php
2026_06_07_800006_create_faculty_workload_summary_table.php  (or compute on-the-fly)
```

New models needed: `TeacherAvailability`, `TimetableVersion`, `TimetableSubstitution`, `ElectiveRegistrationWindow`, `SubjectFacultyAssignment`

Update existing: `TimetableEntry` — add `timetable_version_id` FK; `ProgramSubject` — confirm `elective_group` is usable

---

#### Sprint 1 — Dashboard + Curriculum Management

- PMC-S1-1: Dashboard rebuild — at-risk students widget, faculty workload summary, timetable status (published/draft), upcoming exam dates, elective registration status
- PMC-S1-2: Curriculum manager — add/edit/remove subjects from program-term; set type; set credit weights
- PMC-S1-3: Elective pool manager — define elective groups, float for current term, set registration window
- PMC-S1-4: Subject-faculty assignment — map teacher → subject → term → batch; check workload before assigning
- PMC-S1-5: Elective demand view — real-time registration counts per elective; section capacity management
- PMC-S1-6: Assessment component setup — define IA1/IA2/End-Sem weights per subject-term

---

#### Sprint 2 — Timetable Builder

- PMC-S2-1: Timetable builder UI — grid of day × slot for a batch; assign subject+teacher+room per cell
- PMC-S2-2: Conflict detection — validate on each slot assignment; block save if teacher/room/batch clash
- PMC-S2-3: Teacher availability overlay — show which slots a teacher has marked unavailable/preferred
- PMC-S2-4: Room utilization view — see which rooms are free for a given slot
- PMC-S2-5: Draft → Publish workflow — draft timetable visible only to PMC; publish makes it live for all
- PMC-S2-6: Substitution management — mark teacher absent for a date; assign substitute; auto-notify students
- PMC-S2-7: Extra class scheduler — one-off session outside regular timetable
- PMC-S2-8: Timetable PDF export — print-ready per batch; teacher-wise schedule PDF

---

#### Sprint 3 — Student Oversight

- PMC-S3-1: At-risk student view — filterable by attendance %, CGPA, arrears, fee status; bulk alert
- PMC-S3-2: Mentor assignment — assign/change faculty mentor per student; bulk assign by batch
- PMC-S3-3: Leave approvals — review + approve/reject student leave applications
- PMC-S3-4: Condonation review — review attendance condonation requests; approve with session count
- PMC-S3-5: Grievance management — program-level grievance inbox; assign + resolve + escalate
- PMC-S3-6: Elective override — change a specific student's elective registration (with reason)
- PMC-S3-7: Detention/ATKT register — detained students list with subject-wise arrear history
- PMC-S3-8: Batch promotion processing — view eligibility; promote batch; flag detained students

---

#### Sprint 4 — Faculty Oversight + Reporting

- PMC-S4-1: Faculty workload monitor — hours/week per teacher; flag over/under load
- PMC-S4-2: Marks submission tracker — which subjects have IA marks submitted vs pending
- PMC-S4-3: Marks appeals review — program-level marks appeal inbox; forward to teacher or override
- PMC-S4-4: Course delivery tracking — syllabus coverage % per subject; sessions conducted vs planned
- PMC-S4-5: Faculty feedback aggregation — anonymous student ratings per teacher; trend view
- PMC-S4-6: Subject performance report — marks distribution; compare across batches and terms
- PMC-S4-7: Attendance defaulter export — below-75% students with parent contact info
- PMC-S4-8: Term-end summary PDF — enrollment stats, pass/fail, CGPA distribution, top performers

---

### Cross-Role Interactions (PMC ↔ others)

| PMC Action | Who sees it |
|-----------|-------------|
| Publishes timetable | Students (student.timetable), Teachers (teacher.timetable) |
| Assigns subject to teacher | Teacher gets new subject in their portal |
| Floats electives + opens registration | Students see in student.subjects.index |
| Approves leave | Student leave status updates in student.leave.index |
| Approves condonation | Student condonation status updates |
| Assigns mentor | Student sees mentor in student.mentor.index |
| Publishes academic event | Students see in student.calendar.index |
| Sends grievance to HOD | HOD sees in their grievance inbox |
| Finalizes elective sections | Students enrolled in their confirmed elective |

---

### Key Design Decisions for Implementation

1. **Timetable builder UX**: Use a day × slot HTML table with `<select>` dropdowns in each cell. On change, fire AJAX to `/program-chair/timetable/check-conflict` returning JSON. Save entire draft via one form POST. No drag-and-drop (too complex, poor mobile UX).

2. **Conflict detection logic** (in a `TimetableConflictService`):
   ```php
   // Teacher clash: same teacher, same slot, same day, overlapping term
   // Room clash: same classroom, same slot, same day
   // Batch clash: same batch, same slot, same day (different subjects)
   // Check against both the current draft AND all published entries for the term
   ```

3. **Elective registration flow**:
   - PMC creates `ElectiveRegistrationWindow` (opens_at, closes_at, max_per_student)
   - Students register via `student.subjects.*` (already exists)
   - PMC sees live counts; after window closes, PMC confirms/overrides assignments
   - Confirmed enrollments create `StudentSubjectEnrollment` records with `enrollment_type = elective`

4. **Faculty workload calculation**: Query `timetable_entries` where `teacher_id = X` for the current term; sum `timetable_slots.end_time - start_time` across all entries. Display as hours/week.

5. **Assessment components** are already in `assessment_components` table (used by Exam Cell). PMC sets them up at term start; Exam Cell enters marks later. Don't duplicate the model.

6. **At-risk threshold defaults** (configurable per program):
   - Attendance < 75% in ANY subject → attendance risk
   - CGPA < 5.0 → academic risk
   - Any `exam_results` with marks < passing_marks AND no supplementary attempt → arrear risk
   - Any `fee_demands` with status = pending AND due_date < today → financial risk

---

### File Locations for PMC Sprint

```
app/Http/Controllers/Departmental/ProgramChairController.php  — main controller (extend this)
resources/views/departmental/program-chair/                   — all PMC views (6 files exist; add more)
routes/web.php                                                — program-chair routes (~line 390-430)
app/Models/ProgramSubject.php                                 — already exists
app/Models/TimetableEntry.php                                 — already exists
app/Models/TimetableSlot.php                                  — already exists
app/Models/Teacher.php                                        — already exists
app/Models/Classroom.php                                      — already exists
app/Services/TimetableService.php                             — already exists (check methods)
```

---

## Future Sprints (After PMC)

### 3. Teacher / Faculty Portal
Key features: Mark attendance (per session, from timetable), upload study materials, create/grade assignments, create quizzes, enter IA marks, view own timetable, mentor dashboard (see mentee list, messages, meetings), subject announcements, view course feedback ratings.

### 4. Exam Cell Portal
Key features: Schedule exams, create assessment components, accept marks from teachers, moderate/publish results, manage malpractice logs, generate hall tickets (admit cards), manage supplementary/ATKT exams, process marks appeals, term promotion batch processing.

### 5. Accounts / Finance Portal
Key features: Verify fee payment proofs (from students), generate receipts, manage fee demands by batch, process refunds, scholarship disbursement, generate NAAC financial reports, pending dues dashboard, fee waiver approvals.

### 6. HOD Portal
Key features: Department faculty roster, workload oversight, leave approvals (faculty), departmental grievance resolution, mentor assignment oversight, department-level performance dashboard, faculty appraisal inputs.

### 7. CMC / Placement Portal
Key features: Create/manage placement drives, manage company relationships, assign internships to students, track student placement status, alumni management, career events, placement statistics dashboard.

### 8. Admin Portal
Key features: User management, role assignments, system configuration, audit logs, batch/program/term CRUD, fee structure management, bulk operations, data export.

---

## Phase History

**All 8 infrastructure phases COMPLETE (2026-06-07):**
Role hierarchy, dashboards, approval workflows, offer/enrollment, academic lifecycle, fee management, placement/career, reporting/analytics.

**Student Portal COMPLETE (2026-06-07, PR #15):**
4 sprints, 28 new tables, 25+ controllers, 40+ views.
